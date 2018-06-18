<?php

namespace App\Controller;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class HomeController extends Controller
{
    private $logger;
    public $placesArray = array();
    public $responseArray = array('nextPageToken' => null, 'zipCode' => null);
    public $zipCode = "";
    public $geocodeAPIKey = "AIzaSyDAuY7X8QMRsNzsQyDSsRZZY4dfFMldC1Q";
    public $placesAPIKey = "AIzaSyB3kwqSnpj8zAj7LjyYZxqO0pUWZfsmiXY";

    /**
     * @Route("/", name="Matt Dunbar")
     */
    public function index()
    {
        //Return template index page
        //return $this->render('home/index.html.twig');

        //return new Response(file_get_contents('index.html'));
        return $this->redirect('index.html');

    }

    /**
     * @Route("/resume", name="Resume")
     */
    public function resume()
    {
        return $this->redirect('Resume.pdf');
        //return new Response(file_get_contents('Resume.pdf'));
    }

    /**
     * @Route("/grubby", name="Grubby API")
     */
    public function grubby(Request $request) {


        //Get Latitude/Longitude from JSON payload
        $payload = $request->getContent();
        if (empty($payload)) {
            return new JsonResponse(['error' => 'You tryna peep my API dawg?'], 409);
        }

        $params = json_decode($payload, true);

        $lat = isset($params['latitude']) ? $params['latitude'] : null;
        $lng = isset($params['longitude']) ? $params['longitude'] : null;
        $zipCode = isset($params['zipCode']) ? $params['zipCode'] : null;
        $nextPageToken = isset($params['nextPageToken']) ? $params['nextPageToken'] : null;

        if ($lat == null || $lng == null) {
            return new JsonResponse(['error' => 'Invalid latitude and longitude.'], 409);
        }
        else {
            if ($zipCode == null) {
                //First request, get the zip and then do places request
                $requestURL = "https://maps.googleapis.com/maps/api/geocode/json?latlng=LAT_REPLACE,LNG_REPLACE&key=KEY_REPLACE";
                $requestURL = str_replace("LAT_REPLACE", $lat, $requestURL);
                $requestURL = str_replace("LNG_REPLACE", $lng, $requestURL);
                $requestURL = str_replace("KEY_REPLACE", $this->geocodeAPIKey, $requestURL);
                $requestURL = $this->getZipRequest($requestURL);
                $this->doPlacesRequest($requestURL);
            }
            else {
                //We have the zip code, get the next page token as well and do places request
                if ($nextPageToken != null) {

                    //Go ahead and set zip code again
                    $this->responseArray['zipCode'] = $zipCode;
                    $nextPageURL = "https://maps.googleapis.com/maps/api/place/textsearch/json?pagetoken=TOKEN_REPLACE&query=restaurants+in+ZIP_REPLACE&key=KEY_REPLACE";
                    $nextPageURL = str_replace("TOKEN_REPLACE", $nextPageToken, $nextPageURL);
                    $nextPageURL = str_replace("ZIP_REPLACE", $this->zipCode, $nextPageURL);
                    $nextPageURL = str_replace("KEY_REPLACE", $this->placesAPIKey, $nextPageURL);
                    $this->doPlacesRequest($nextPageURL);
                }
                else {
                    //If it's null, there's no more places to get
                    return new JsonResponse(['error' => 'No more places to get.'], 420);
                }
            }
        }


        //return new JsonResponse(['count' => count($this->placesArray)]);
        $this->logger->info(count($this->placesArray));

        $this->responseArray['results'] = $this->placesArray;
        return new JsonResponse($this->responseArray);

    }

    public function getZipRequest($requestURL) {

        $response = file_get_contents($requestURL);
        $result_array = json_decode($response);

        if ($result_array->status == "OK") {

            if (isset($result_array->results) && array_key_exists(0, $result_array->results)) {
                $addressComponents = $result_array->results[0]->address_components;
                for ($i = 0; $i < count($addressComponents); $i++) {
                    if ($addressComponents[$i]->types != null) {
                        $types = $addressComponents[$i]->types;
                        for ($j = 0; $j < count($types); $j++) {
                            if (strcmp($types[$j],'postal_code') == 0) {
                                $this->zipCode = $addressComponents[$i]->long_name;

                                //Add zipCode to response
                                $this->responseArray['zipCode'] = $this->zipCode;

                                break;
                            }
                        }
                    }
                }
            }
            else {
                return new JsonResponse(['error' => 'Could not obtain zip code.'], 409);
            }
        }
        else {
            return new JsonResponse(['error' => 'Places API request failed.'], 409);
        }

        $requestURL = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=restaurants+in+ZIP_REPLACE&key=KEY_REPLACE";
        $requestURL = str_replace("ZIP_REPLACE", $this->zipCode, $requestURL);
        $requestURL = str_replace("KEY_REPLACE", $this->placesAPIKey, $requestURL);

        return $requestURL;

    }

    public function doPlacesRequest($requestURL) {

        //Get JSON response from Google Places
        $response = json_decode(file_get_contents($requestURL));

        //Get results key
        $results = $response->results;

        //Parse the places
        $this->parsePlaces($results);

        //If there's a next page token, form a new request with the new page and run this function again
        //to parse more results
        if (isset($response->next_page_token)) {
            $nextPageToken = $response->next_page_token;
            $this->responseArray['nextPageToken'] = $nextPageToken;
        }

    }

    public function parsePlaces($results) {

        foreach($results AS $result) {
            $place = [];
            $place['address'] = isset($result->formatted_address) ? $result->formatted_address : null;
            $place['latitude'] = isset($result->geometry->location->lat) ? $result->geometry->location->lat : null;
            $place['longitude'] = isset($result->geometry->location->lng) ? $result->geometry->location->lng : null;
            $place['name'] = isset($result->name) ? $result->name : null;

            if (isset($result->opening_hours)) {
                if (isset($result->opening_hours->open_now)) {
                    $place['open'] = $result->opening_hours->open_now;
                } else { $place['open'] = null; }
            } else { $place['open'] = null; }

            $place['rating'] = isset($result->rating) ? $result->rating : null;

            if (isset($result->photos)) {
                if (array_key_exists(0, $result->photos)) {

                    $photoRef = isset($result->photos[0]->photo_reference) ? $result->photos[0]->photo_reference : null;
                    $imageURL = null;
                    if ($photoRef != null) {
                        $imageURL = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=REFERENCE_REPLACE&key=KEY_REPLACE";
                        $imageURL = str_replace("KEY_REPLACE", $this->placesAPIKey, $imageURL);
                        $imageURL = str_replace("REFERENCE_REPLACE", $photoRef, $imageURL);
                    }

                    $place['imageURL'] = $imageURL;
                }
            }
            $this->placesArray[] = $place;
        }

    }



    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

}
