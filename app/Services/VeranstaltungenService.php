<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use DOMDocument;
use SimpleXMLElement;
use DOMXPath;

class VeranstaltungenService
{
    public $response;
    public $entries;

    public function all(): Collection
    {
        $this->entries = Cache::get('veranstaltungen');

        if (!$this->entries) {
            $this->request();
            $this->validate();
            $this->transform();
            $this->cache();
        }

        return $this->entries;
    }

    public function cache(): void
    {
        // put the result into caching
        Cache::put('veranstaltungen', $this->entries, now()->addYears(1));
    }

    public function transform(): Collection
    {
        $xml = new DOMDocument();
        $xml->loadXML($this->response);

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('soapEnv', 'http://www.w3.org/2003/05/soap-envelope');
        $xpath->registerNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');
        $xpath->registerNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xpath->registerNamespace('tns', 'http://www.oorsprong.org/websamples.countryinfo');

        $continents = $xpath->evaluate('//tns:ListOfContinentsByNameResult/tns:tContinent');

        $this->entries = collect();
        foreach ($continents as $continent) {
            $this->entries->push([
                'code' => $continent->getElementsByTagName('sCode')->item(0)->nodeValue,
                'name' => $continent->getElementsByTagName('sName')->item(0)->nodeValue,
            ]);
        }

        return $this->entries;
    }

    public function validate(): ?array
    {
        libxml_use_internal_errors(true);

        $xml_schema_url = config('services.core.veranstaltungen') . '?WSDL';
        $xmlEnvelope = $this->response;

        //extracting schema from WSDL
        $xml = new DOMDocument();
        $wsdl_string = file_get_contents($xml_schema_url);

        //extracting namespaces from WSDL
        $outer = new SimpleXMLElement($wsdl_string);
        $wsdl_namespaces = $outer->getDocNamespaces();

        //extracting the schema tag inside WSDL
        $xml->loadXML($wsdl_string);
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');
        $schemaNode = $xpath->evaluate('//xsd:schema');

        $schemaXML = "";
        foreach ($schemaNode as $node) {

            //add namespaces from WSDL to schema
            foreach ($wsdl_namespaces as $prefix => $ns) {
                $node->setAttribute("xmlns:$prefix", $ns);
            }
            $schemaXML .= simplexml_import_dom($node)
                ->asXML();
        }

        //capturing de XML envelope
        $xml = new DOMDocument();
        $xml->loadXML($xmlEnvelope);

        //extracting namespaces from soap Envelope
        $outer = new SimpleXMLElement($xmlEnvelope);
        $envelope_namespaces = $outer->getDocNamespaces();

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('soapEnv', 'http://www.w3.org/2003/05/soap-envelope');
        $envelopeBody = $xpath->evaluate('//soapEnv:Body/*[1]');

        $envelopeBodyXML = "";
        foreach ($envelopeBody as $node) {

            //add namespaces from envelope to the body content
            foreach ($envelope_namespaces as $prefix => $ns) {
                $node->setAttribute("xmlns:$prefix", $ns);
            }
            $envelopeBodyXML .= simplexml_import_dom($node)
                ->asXML();
        }

        $doc = new DOMDocument();
        $doc->loadXML($envelopeBodyXML); // load xml
        $is_valid_xml = $doc->schemaValidateSource($schemaXML); // path to xsd file

        if (!$is_valid_xml) {
            throw new \Exception('Failed to validate the response from the Veranstaltungen API');
            return libxml_get_errors();
        }

        return null;
    }

    public function request(): string
    {
        $client = new Client();
        // if there is an Auth Layer
        // Path to the SSL certificate (.pfx) file
        // $certificatePath = storage_path(config('services.certificate.path'));
        // Password for the SSL certificate
        // $certificatePassword = config('services.certificate.password');

        // API endpoint URL
        $endpointUrl = config('services.core.veranstaltungen');

        // Request payload
        $soap_envelope = $this->prepareSoapEnvelope();

        // Header
        $header = [
            'Content-Type' => 'application/soap+xml; charset=utf-8'
        ];

        // add certificate to the request
        // $auth = [
        //     // add these
        //     'cert' => $certificatePath,
        //     'ssl_key' => $certificatePassword
        // ];

        // Send the request
        $response = $client->post($endpointUrl, [
            'headers' => $header,
            'body' => $soap_envelope,
            // 'auth' => $auth
        ]);

        // check if status code is 200
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to fetch data from the Veranstaltungen API');
        }

        $this->response = $response->getBody()->getContents();

        return $this->response;
    }

    private function prepareSoapEnvelope(): string
    {
        $envelope = new \SimpleXMLElement('<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"></soap12:Envelope>');
        $body = $envelope->addChild('soap12:Body');
        $body->addChild('ListOfContinentsByName', null, 'http://www.oorsprong.org/websamples.countryinfo');
        return $envelope->asXML();
    }
}
