<?php namespace RD;

use Exception;

class RDStationAPI
{
    public $token;
    public $privateToken;
    public $baseURL = "https://www.rdstation.com.br/api/";
    public $defaultIdentifier = "rdstation-php-integration";

    /**
     * @param null $privateToken
     * @param null $token
     */
    public function __construct($privateToken = NULL, $token = NULL)
    {
        if (empty($privateToken)) throw new Exception("Inform RDStationAPI.privateToken as the first argument.");

        $this->token = $token;
        $this->privateToken = $privateToken;
    }

    /**
     * @param string $type generic, leads, conversions
     * @param string $apiVersion
     * @return string
     */
    protected function getURL($type = 'generic', $apiVersion = '1.2')
    {
        //(POST) https://www.rdstation.com.br/api/1.2/services/PRIVATE_TOKEN/generic //USED TO CHANGE A LEAD STATUS
        //(PUT) https://www.rdstation.com.br/api/1.2/leads/:lead_email //USED TO UPDATE A LEAD
        //(POST) https://www.rdstation.com.br/api/1.2/conversions //USED TO SEND A NEW LEAD
        switch ($type) {
            case 'generic':
                return $this->baseURL . $apiVersion . "/services/" . $this->privateToken . "/generic";
            case 'leads':
                return $this->baseURL . $apiVersion . "/leads/";
            case 'conversions':
                return $this->baseURL . $apiVersion . "/conversions";
        }

        return '';
    }

    /**
     * @throws \Exception
     */
    protected function validateToken()
    {
        if (empty($this->token)) {
            throw new Exception("Inform RDStation.token as the second argument when instantiating a new RDStationAPI object.");
        }
    }

    /**
     * @param string $method POST, PUT
     * @param $url RD Station endpoint returned by $this->getURL()
     * @param array $data
     * @return bool
     */
    protected function request($method = "POST", $url, $data = array())
    {
        $data['token_rdstation'] = $this->token;
        $JSONData = json_encode($data);
        $URLParts = parse_url($url);

        $fp = fsockopen($URLParts['host'],
            isset($URLParts['port']) ? $URLParts['port'] : 80,
            $errno, $errstr, 30);

        $out = $method . " " . $URLParts['path'] . " HTTP/1.1\r\n";
        $out .= "Host: " . $URLParts['host'] . "\r\n";
        $out .= "Content-Type: application/json\r\n";
        $out .= "Content-Length: " . strlen($JSONData) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        $out .= $JSONData;
        $written = fwrite($fp, $out);
        fclose($fp);

        return ($written == false) ? false : true;
    }

    /**
     * @param $email The email of the lead
     * @param array $data Custom data array, example
     * @return bool
     * @throws \Exception
     */
    public function sendNewLead($email, $data = array())
    {
        /**
         * Data:
         * array(
         * "identificador" => "contact-form",
         * "nome" => "Júlio Paulillo",
         * "empresa" => "Agendor",
         * "cargo" => "Cofounder",
         * "telefone" => "(11) 3280-8090",
         * "celular" => "(11) 99999-9999",
         * "website" => "www.agendor.com.br",
         * "twitter" => "twitter.com/paulillo",
         * "facebook" => "facebook.com/paulillo",
         * "c_utmz" => "",
         * "created_at" => "",
         * "tags" => "cofounder, hotlead"
         * );
         **/

        $this->validateToken();

        if (empty($email)) {
            throw new Exception("Inform at least the lead email as the first argument.");
        }
        if (empty($data['identificador'])) {
            $data['identificador'] = $this->defaultIdentifier;
        }
        if (empty($data["client_id"]) && !empty($_COOKIE["rdtrk"])) {
            $data["client_id"] = json_decode($_COOKIE["rdtrk"])->{'id'};
        }
        if (empty($data["traffic_source"]) && !empty($_COOKIE["__trf_src"])) {
            $data["traffic_source"] = $_COOKIE["__trf_src"];
        }

        $data['email'] = $email;

        return $this->request("POST", $this->getURL('conversions'), $data);
    }

    /**
     * Helper function to update lead properties.
     *
     * @param $email
     * @param array $data
     * @return bool
     */
    public function updateLead($email, $data = array())
    {
        $newData = [];
        $url = $this->getURL('leads', '1.3') . $email;
        $newData['lead'] = $data;
        $newData['auth_token'] = $this->privateToken;

        return $this->request("PUT", $url, $newData);
    }

    /**
     * @param $email Lead email
     * @param int $newStage 0 - Lead, 1 - Qualified Lead, 2 - Customer
     * @param bool $opportunity
     * @return bool
     * @throws \Exception
     */
    public function updateLeadStageAndOpportunity($email, $newStage = 0, $opportunity = false)
    {
        if (empty($email)) {
            throw new Exception("Inform lead email as the first argument.");
        }

        $url = $this->getURL('leads') . $email;

        $data = [
            "auth_token" => $this->privateToken,
            "lead" => [
                "lifecycle_stage" => $newStage,
                "opportunity" => $opportunity
            ]
        ];

        return $this->request("PUT", $url, $data);
    }

    /**
     * @param string|int $emailOrLeadId Lead email OR Lead unique custom ID
     * @param string $status won or lost
     * @param int|float $value Purchase value
     * @param string|null $lostReason
     * @return bool
     * @throws \Exception
     */
    public function updateLeadStatus($emailOrLeadId, $status, $value = NULL, $lostReason = NULL)
    {
        if (empty($emailOrLeadId)) {
            throw new Exception("Inform lead email or unique custom ID as the first argument.");
        }
        if (empty($status)) {
            throw new Exception("Inform lead status as the second argument.");
        } else if ($status != "won" && $status != "lost") {
            throw new Exception("Lead status (second argument) should be 'won' or 'lost'.");
        }

        $data = [
            "status" => $status,
            "value" => $value,
            "lost_reason" => $lostReason,
        ];

        if (is_integer($emailOrLeadId)) {
            $data["lead_id"] = $emailOrLeadId;
        } else {
            $data["email"] = $emailOrLeadId;
        }

        return $this->request("POST", $this->getURL('generic'), $data);
    }
}
