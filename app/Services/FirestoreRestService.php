<?php

namespace App\Services;

use App\Traits\FirebaseAuthTrait;
use Illuminate\Support\Facades\Http;

class FirestoreRestService
{

    use FirebaseAuthTrait;
    public $authToken;

    public function refreshAuth($force = false)
    {
        $this->authToken = setting("serverFBAuthToken", "");
        $tokenExpiry = ((int) setting("serverFBAuthTokenExpiry", 0)) ?? 0;
        //
        if ($force || $tokenExpiry < now()->milliseconds) {
            $uId = "web_server";
            $firebaseAuth = $this->getFirebaseAuth();
            $customToken = $firebaseAuth->createCustomToken($uId);
            $signInResult = $firebaseAuth->signInWithCustomToken($customToken);
            $this->authToken = $signInResult->idToken();
            //generate new tokens 5mintues to its expiry
            $tokenExpiry = (now()->milliseconds + ($signInResult->ttl() ?? 0)) - 300000;
            //
            setting([
                "serverFBAuthToken" => $this->authToken,
                "serverFBAuthTokenExpiry" => $tokenExpiry,
            ])->save();
        }
    }

    //
    public function whereBetween($earthDistanceToNorth, $earthDistanceToSouth, $rejectedDriversCount)
    {

        //
        $this->refreshAuth();
        //
        $maxDriverOrderNotificationAtOnce = (int) setting('maxDriverOrderNotificationAtOnce', 1) + $rejectedDriversCount;
        $baseUrl = "https://firestore.googleapis.com/v1/projects/" . setting("projectId", "") . "/databases/(default)/documents/:runQuery";

        // logger("Search range", [$earthDistanceToNorth, $earthDistanceToSouth, $maxDriverOrderNotificationAtOnce]);
        //
        $response = Http::withToken($this->authToken)->post(
            $baseUrl,
            [
                'structuredQuery' => [
                    'where' => [
                        'compositeFilter' => [
                            'op' => 'AND',
                            'filters' => [
                                0 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'earth_distance',
                                        ],
                                        'op' => 'LESS_THAN_OR_EQUAL',
                                        'value' => [
                                            'doubleValue' => $earthDistanceToNorth,
                                        ],
                                    ],
                                ],
                                1 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'earth_distance',
                                        ],
                                        'op' => 'GREATER_THAN_OR_EQUAL',
                                        'value' => [
                                            'doubleValue' => $earthDistanceToSouth,
                                        ],
                                    ],
                                ],
                                2 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'online',
                                        ],
                                        'op' => 'EQUAL',
                                        'value' => [
                                            'doubleValue' => 1,
                                        ],
                                    ],
                                ],
                                3 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'free',
                                        ],
                                        'op' => 'EQUAL',
                                        'value' => [
                                            'doubleValue' => 1,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'from' => [
                        0 => [
                            'collectionId' => 'drivers',
                        ],
                    ],
                    'limit' => $maxDriverOrderNotificationAtOnce,
                ],
            ]
        );

        //
        $drivers = [];
        if ($response->ok()) {
            $driversRawData = $response->json();
            foreach ($driversRawData as $driver) {
                if (empty($driver["document"])) {
                    continue;
                }
                //else get driver data
                try {

                    $drivers[] = [
                        "id" => $driver["document"]["fields"]["id"]["stringValue"] ?? $driver["document"]["fields"]["id"]["integerValue"],
                        "lat" => $driver["document"]["fields"]["lat"]["doubleValue"],
                        "long" => $driver["document"]["fields"]["long"]["doubleValue"],
                        "earth_distance" => $driver["document"]["fields"]["earth_distance"]["doubleValue"],
                    ];
                } catch (\Exception $ex) {
                    logger("Driver details Error", [$ex->getMessage()]);
                }
            }
        } else {
            $errorCode = $response->json()[0]["error"]["code"];
            if ($errorCode == 401 || $errorCode == 403) {
                $this->refreshAuth(true);
            }

            try {


                if ($response->json()[0]["error"]["status"] == "FAILED_PRECONDITION") {
                    logger("Please follow this link to create the required condition on your firebase", [$response->json()[0]["error"]["message"]]);

                    $error = "Please follow this link to create the required condition on your firebase \n" . $response->json()[0]["error"]["message"] . "";
                    throw new \Exception($error, 1);
                } else {
                    logger("Error with drivers search", [$response->body()]);
                }
            } catch (\Exception $ex) {
                logger("Error with drivers search", [$ex->getMessage() ?? $response->body()]);
                throw new \Exception($ex->getMessage() ?? $response->body(), 1);
            }
            // throw new \Exception($response->body(), 1);
        }

        return $drivers;
    }

    //
    public function whereAvailableTaxiDriversBetween($earthDistanceToNorth, $earthDistanceToSouth, $vehicleTypeId, $rejectedDriversCount)
    {

        //
        $this->refreshAuth();
        //
        $maxDriverOrderNotificationAtOnce = (int) setting('maxDriverOrderNotificationAtOnce', 1) + $rejectedDriversCount;
        $baseUrl = "https://firestore.googleapis.com/v1/projects/" . setting("projectId", "") . "/databases/(default)/documents/:runQuery";

        // logger("Search range", [$earthDistanceToNorth, $earthDistanceToSouth, $maxDriverOrderNotificationAtOnce]);
        //
        $response = Http::withToken($this->authToken)->post(
            $baseUrl,
            [
                'structuredQuery' => [
                    'where' => [
                        'compositeFilter' => [
                            'op' => 'AND',
                            'filters' => [
                                0 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'earth_distance',
                                        ],
                                        'op' => 'LESS_THAN_OR_EQUAL',
                                        'value' => [
                                            'doubleValue' => $earthDistanceToNorth,
                                        ],
                                    ],
                                ],
                                1 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'earth_distance',
                                        ],
                                        'op' => 'GREATER_THAN_OR_EQUAL',
                                        'value' => [
                                            'doubleValue' => $earthDistanceToSouth,
                                        ],
                                    ],
                                ],
                                2 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'vehicle_type_id',
                                        ],
                                        'op' => 'EQUAL',
                                        'value' => [
                                            'doubleValue' => $vehicleTypeId,
                                        ],
                                    ],
                                ],
                                3 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'online',
                                        ],
                                        'op' => 'EQUAL',
                                        'value' => [
                                            'doubleValue' => 1,
                                        ],
                                    ],
                                ],
                                4 => [
                                    'fieldFilter' => [
                                        'field' => [
                                            'fieldPath' => 'free',
                                        ],
                                        'op' => 'EQUAL',
                                        'value' => [
                                            'doubleValue' => 1,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'from' => [
                        0 => [
                            'collectionId' => 'drivers',
                        ],
                    ],
                    'limit' => $maxDriverOrderNotificationAtOnce,
                ],
            ]
        );

        //
        $drivers = [];
        if ($response->ok()) {
            $driversRawData = $response->json();
            foreach ($driversRawData as $driver) {
                if (empty($driver["document"])) {
                    continue;
                }
                //else get driver data
                try {

                    // logger("Driver doc", [$driver["document"]["fields"]]);
                    $drivers[] = [
                        "id" => $driver["document"]["fields"]["id"]["stringValue"] ?? $driver["document"]["fields"]["id"]["integerValue"],
                        "lat" => $driver["document"]["fields"]["lat"]["doubleValue"],
                        "long" => $driver["document"]["fields"]["long"]["doubleValue"],
                        "earth_distance" => $driver["document"]["fields"]["earth_distance"]["doubleValue"],
                    ];
                } catch (\Exception $ex) {
                    logger("Driver details Error", [$ex->getMessage()]);
                }
            }
        } else {
            $errorCode = $response->json()[0]["error"]["code"];
            if ($errorCode == 401 || $errorCode == 403) {
                $this->refreshAuth(true);
            }

            try {


                if ($response->json()[0]["error"]["status"] == "FAILED_PRECONDITION") {
                    logger("Please follow this link to create the required condition on your firebase", [$response->json()[0]["error"]["message"]]);
                } else {
                    logger("Error with drivers search", [$response->body()]);
                }
            } catch (\Exception $ex) {
                logger("Error with drivers search", [$response->body()]);
            }
            // throw new \Exception($response->body(), 1);
        }

        return $drivers;
    }

    //
    public function exipredDriverNewOrders()
    {

        //
        $this->refreshAuth();
        //fetch firebase server timestamp

        //
        $baseUrl = "https://firestore.googleapis.com/v1/projects/" . setting("projectId", "") . "/databases/(default)/documents/:runQuery";
        $response = Http::withToken($this->authToken)->post(
            $baseUrl,
            [
                'structuredQuery' => [
                    // 'where' => [
                    //     'fieldFilter' => [
                    //         'field' => [
                    //             'fieldPath' => 'exipres_at_timestamp',
                    //         ],
                    //         'op' => 'LESS_THAN_OR_EQUAL',
                    //         'value' => [
                    //             'timestampValue' => "REQUEST_TIME",
                    //         ],
                    //     ],
                    // ],
                    'orderBy' => [
                        'field' => [
                            'fieldPath' => 'exipres_at',
                        ],
                        'direction' => 'ASCENDING',
                    ],
                    'from' => [
                        0 => [
                            'collectionId' => 'driver_new_order',
                        ],
                    ],
                    'limit' => 20,
                ],
            ]
        );

        //
        //
        $drivers = [];
        if ($response->ok()) {
            $driversRawData = $response->json();
            foreach ($driversRawData as $driver) {
                if (empty($driver["document"])) {
                    continue;
                }
                //else get driver data
                try {

                    // logger("driver", [$driver]);
                    $docName = $driver["document"]["name"];
                    $docName = explode("/", $docName);
                    $docNameCount = count($docName);
                    $docName = $docName[$docNameCount - 1];
                    //
                    $currentTime = new \Carbon\Carbon($driver["readTime"]);

                    $drivers[] = $driverObject =  [
                        "id" => $docName,
                        "exipres_at" => $driver["document"]["fields"]["exipres_at"]["integerValue"],
                        "current_time" => $currentTime->timestamp * 1000,
                    ];
                } catch (\Exception $ex) {
                    logger("Driver details Error", [$ex->getMessage()]);
                }
            }
        } else {
            $errorCode = $response->json()[0]["error"]["code"];
            if ($errorCode == 401 || $errorCode == 403) {
                $this->refreshAuth(true);
            }
            logger("Error with drivers search", [$response->body()]);
            // throw new \Exception($response->body(), 1);
        }

        return $drivers;
    }

    //
    public function deleteDocument($path, $recursive = true)
    {

        //
        $this->refreshAuth();

        //get the collections in that path
        $collectionIDs = $this->getCollections($path);
        if (empty($collectionIDs)) {
            $this->deleteDocumentData($path);
        } else {

            foreach ($collectionIDs as $collectionID) {
                $collectionPath = "{$path}/{$collectionID}";
                $this->deleteDocument($collectionPath);
            }
        }
    }

    public function getCollections($path): array
    {
        //
        $projectId = setting("projectId", "");
        $baseUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$path}/:listCollectionIds";
        $response = Http::withToken($this->authToken)->post($baseUrl, [
            "pageSize" => 20,
            // "pageToken" => null,
        ]);

        //
        $collections = [];
        if ($response->ok()) {
            logger("collections", [$response->json()]);
            $collections = $response->json()["collectionIds"];
        } else {
            logger("collections fetch error", [$response->json()]);
            $errorCode = ($response->json()[0] ??  $response->json())["error"]["code"];
            if ($errorCode == 401 || $errorCode == 403) {
                $this->refreshAuth(true);
            }

            try {


                if ($response->json()[0]["error"]["status"] == "FAILED_PRECONDITION") {
                    logger("Please follow this link to create the required condition on your firebase", [$response->json()[0]["error"]["message"]]);
                } else {
                    logger("Error with drivers search", [$response->body()]);
                }
            } catch (\Exception $ex) {
                logger("Error with drivers search", [$response->body()]);
            }
            // throw new \Exception($response->body(), 1);
        }

        return $collections;
    }

    public function deleteDocumentData($path)
    {

        $baseUrl = "https://firestore.googleapis.com/v1/projects/" . setting("projectId", "") . "/databases/(default)/documents/{$path}";
        $response = Http::withToken($this->authToken)->delete($baseUrl);

        //
        if (!$response->ok()) {
            $errorCode = $response->json()[0]["error"]["code"];
            if ($errorCode == 401 || $errorCode == 403) {
                $this->refreshAuth(true);
            }
            logger("Error with deleting documents", [$response->body()]);
            // throw new \Exception($response->body(), 1);
        }
    }
}
