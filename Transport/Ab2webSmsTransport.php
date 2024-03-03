<?php
/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author      Jan Kozak <galvani78@gmail.com>
 */

namespace MauticPlugin\MauticAb2webSmsBundle\Transport;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\SmsBundle\Api\AbstractSmsApi;
use Monolog\Logger;
use GuzzleHttp\Client;

class Ab2webSmsTransport extends AbstractSmsApi
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var IntegrationHelper
     */
    protected $integrationHelper;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    private $api_key;

    /**
     * @var string
     */
    private $sender_id;

    /**
     * @var bool
     */
    protected $connected;

    /**
     * @param IntegrationHelper $integrationHelper
     * @param Logger            $logger
     * @param Client            $client
     */
    public function __construct(IntegrationHelper $integrationHelper, Logger $logger, Client $client)
    {
        $this->integrationHelper = $integrationHelper;
        $this->logger = $logger;
        $this->client = $client;
        $this->connected = false;
    }

    /**
     * @param Lead   $contact
     * @param string $content
     *
     * @return bool|string
     */
    public function sendSms(Lead $contact, $content)
    {
        $number = $contact->getLeadPhoneNumber();
        if (empty($number)) {
            return false;
        }

        try {
            $number = substr($this->sanitizeNumber($number), 1);
        } catch (NumberParseException $e) {
            $this->logger->addInfo('Invalid number format. ', ['exception' => $e]);
            return $e->getMessage();
        }

        try {
            if (!$this->connected && !$this->configureConnection()) {
                throw new \Exception("Ab2webSms MSG is not configured properly.");
            }

            $content = $this->sanitizeContent($content, $contact);
            if (empty($content)) {
                throw new \Exception('Message content is Empty.');
            }

            $response = $this->send($number, $content);
            $this->logger->addInfo("Ab2webSms MSG request succeeded. ", ['response' => $response]);
            return true;
        } catch (\Exception $e) {
            $this->logger->addError("Ab2webSms MSG request failed. ", ['exception' => $e]);
            return $e->getMessage();
        }
    }

    /**
     * @param integer   $number
     * @param string    $content
     * 
     * @return array
     * 
     * @throws \Exception
     */
    protected function send($number, $content)
    {

        // Inicializa cURL
        $ch = curl_init();

        // Configura la URL a la que se va a enviar la solicitud POST
        curl_setopt($ch, CURLOPT_URL, "https://sms.ab2web.com/services/send.php");

        // Indica a cURL que queremos hacer una solicitud POST
        curl_setopt($ch, CURLOPT_POST, true);

        // Adjunta los datos que se enviarán con la solicitud POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
            "key" => $this->api_key,
            "number" => $number,
            "message" => $content,
            "type" => "sms",
            "prioritize" => "1"
        )));

        // Devuelve la respuesta como una cadena en lugar de mostrarla directamente
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Ignora la verificación de SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Ejecuta la solicitud POST
        $response = curl_exec($ch);

        // Cierra el recurso cURL y libera recursos del sistema
        curl_close($ch);

        // Imprime la respuesta de la solicitud POST
        echo $response;

        $this->logger->addInfo("Ab2webSms MSG API request intiated. ", ['url' => $url]);
    }

    /**
     * @param string $number
     *
     * @return string
     *
     * @throws NumberParseException
     */
    protected function sanitizeNumber($number)
    {
        $util = PhoneNumberUtil::getInstance();
        $parsed = $util->parse($number, 'IN');

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * @return bool
     */
    protected function configureConnection()
    {
        $integration = $this->integrationHelper->getIntegrationObject('Ab2webSms');
        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $keys = $integration->getDecryptedApiKeys();
            // if (empty($keys['api_key']) || empty($keys['sender_id'])) {
            if (empty($keys['api_key']) ) {
	    return false;
            }
            $this->api_key = $keys['api_key'];
//            $this->sender_id = $keys['sender_id'];
            $this->connected = true;
        }
        return $this->connected;
    }

    /**
     * @param string $content
     * @param Lead   $contact
     *
     * @return string
     */
    protected function sanitizeContent(string $content, Lead $contact) {
        return strtr($content, array(
            '{contact_title}' => $contact->getTitle(),
            '{conact_firstname}' => $contact->getFirstname(),
            '{contact_lastname}' => $contact->getLastname(),
            '{contact_lastname}' => $contact->getName(),
            '{contact_company}' => $contact->getCompany(),
            '{contact_email}' => $contact->getEmail(),
            '{contact_address1}' => $contact->getAddress1(),
            '{contact_address2}' => $contact->getAddress2(),
            '{contact_city}' => $contact->getCity(),
            '{contact_state}' => $contact->getState(),
            '{contact_country}' => $contact->getCountry(),
            '{contact_zipcode}' => $contact->getZipcode(),
            '{contact_location}' => $contact->getLocation(),
            '{contact_phone}' => $contact->getLeadPhoneNumber(),
        ));
    }
}
