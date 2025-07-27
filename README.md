# **SS12000 PHP Client Library**

This is a PHP client library designed to simplify interaction with the SS12000 API, a standard for information exchange between school administration processes based on OpenAPI 3.x. The library leverages GuzzleHttp for robust HTTP communication, providing a structured and object-oriented approach to interact with **all** the API's defined endpoints.

You can download your own personal copy of the SS12000 standard for free from here: [sis.se](https://www.sis.se/standarder/kpenstandard/forkopta-standarder/informationshantering-inom-utbildningssektorn/).

### **Important**

The SS12000 does not require the server to support all of the endpoints. You need to actually look at the server documentation to see which endpoints that are actually available with each service. Adding some sort of discovery service is beyond the scope of this small library in my humble opinion.

All dates are in the RFC 3339 format, we're not cavemen here. 

## **Table of Contents**

- [**SS12000 PHP Client Library**](#ss12000-php-client-library)
    - [**Important**](#important)
  - [**Table of Contents**](#table-of-contents)
  - [**Installation**](#installation)
  - [**Usage**](#usage)
    - [**Initializing the Client**](#initializing-the-client)
    - [**Fetching Data with Query Parameters (Filtering, Pagination, Sorting)**](#fetching-data-with-query-parameters-filtering-pagination-sorting)
    - [**Fetching Organizations**](#fetching-organizations)
    - [**Fetching Persons**](#fetching-persons)
    - [**Fetch ...**](#fetch-)
    - [**Webhooks (Subscriptions)**](#webhooks-subscriptions)
  - [**API Reference**](#api-reference)
  - [**Webhook Receiver (PHP Example)**](#webhook-receiver-php-example)
  - [**Contribute**](#contribute)
  - [**License**](#license)

## **Installation**

This library is designed to be installed via Composer.

1. **Ensure Composer is Installed:** If you don't have Composer, download and install it from [getcomposer.org](https://getcomposer.org/download/).  

2. **Create composer.json:** In the root of your PHP project, create a composer.json file with the following content (if you haven't already):  
```
   {
    "require": {
        "php": ">=7.4",
        "guzzlehttp/guzzle": "^7.0",
        "your-vendor-name/ss12000-client": "^0.1"
    }
}
```

3. **Install Dependencies:** Run Composer to install the library and its dependencies (Guzzle).  
```
composer install
```
## **Usage**

### **Initializing the Client**

To start using the client, include Composer's autoloader and create an instance of SS12000\Client\SS12000Client.  

```
<?php

require_once __DIR__ . '/vendor/autoload.php'; // Adjust path if necessary

use SS12000\Client\SS12000Client;  
use GuzzleHttp\Exception\RequestException;

// Replace with your actual test server URL and JWT token  
const BASE_URL = "https://some.server.se/v2.0";  
const AUTH_TOKEN = "YOUR_JWT_TOKEN_HERE";

$client = new SS12000Client(BASE_URL, AUTH_TOKEN);
```

Example usage will be demonstrated in separate functions below.

### **Fetching Data with Query Parameters (Filtering, Pagination, Sorting)**

Most get* methods in the client library accept an associative array as their first argument ($queryParams). This array is used to pass various query parameters for filtering, pagination, sorting, and other API-specific options.

The keys in this array should match the parameter names expected by the SS12000 API (typically camelCase). The client includes a basic mapParamKey helper, but for most standard API parameters, you can use the API's exact parameter names directly.

Common Query Parameters:

* limit: Maximum number of items to return.
* offset: Number of items to skip.
* expand: A list of related resources to expand (e.g., ['duties', 'responsibleFor']).
* expandReferenceNames: Set to true to include displayName for all referenced objects.
* Specific filters: e.g., meta.modifiedAfter, startDate.onOrBefore, etc.

Example: Fetching persons modified after a specific date, with a limit.

```
<?php
// ... (initialization from above)

try {
    echo "\nFetching persons modified after 2024-01-01T00:00:00Z, limited to 5 results...\n";
    $filteredPersons = $client->getPersons([
        'meta.modifiedAfter' => '2024-01-01T00:00:00Z',
        'limit' => 5,
        'offset' => 0, // Start from the beginning
        'expand' => ['duties'] // Also expand duties
    ]);
    echo "Fetched persons (filtered):\n" . json_encode($filteredPersons, JSON_PRETTY_PRINT) . "\n";

} catch (RequestException $e) {
    echo "An HTTP request error occurred during filtered person fetch: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "An unexpected error occurred during filtered person fetch: " . $e->getMessage() . "\n";
}
```

### **Fetching Organizations**

You can retrieve a list of organizations or a specific organization by its ID. Parameters are passed as an associative array.  
```
<?php  
// ... (initialization from above)

async function getOrganizationData(SS12000Client $client) {  
    try {  
        echo "\nFetching organizations...\n";  
        $organizations = $client->getOrganisations(['limit' => 2\]);  
        echo "Fetched organizations: " . json_encode($organizations, JSON_PRETTY_PRINT) . "\n";

        if (!empty($organizations['data'])) {  
            $firstOrgId = $organizations['data'][0]['id'];  
            echo "\nFetching organization with ID: {$firstOrgId}...\n";  
            $orgById = $client->getOrganisationById($firstOrgId, true); // expandReferenceNames = true  
            echo "Fetched organization by ID: " . json_encode($orgById, JSON_PRETTY_PRINT) . "\n";  
        }  
    } catch (RequestException $e) {  
        echo "An HTTP request error occurred fetching organization data: " . $e->getMessage() . "\n";  
    } catch (Exception $e) {  
        echo "An unexpected error occurred fetching organization data: " . $e->getMessage() . "\n";  
    }  
}

```

### **Fetching Persons**

Similarly, you can fetch persons and expand related data such as duties.  
```
<?php  
// ... (initialization from above)

async function getPersonData(SS12000Client $client) {  
    try {  
        echo "\nFetching persons...\n";  
        $persons = $client-\>getPersons(['limit' => 2, 'expand' => ['duties']]);  
        echo "Fetched persons: " . json_encode($persons, JSON_PRETTY_PRINT) . "\n";

        if (!empty($persons['data'])) {  
            $firstPersonId = $persons['data'][0]['id'];  
            echo "\nFetching person with ID: {$firstPersonId}...\n";  
            $personById = $client->getPersonById($firstPersonId, ['duties', 'responsibleFor'], true);  
            echo "Fetched person by ID: " . json_encode($personById, JSON_PRETTY_PRINT) . "\n";  
        }  
    } catch (RequestException $e) {  
        echo "An HTTP request error occurred fetching person data: " . $e->getMessage() . "\n";  
    } catch (Exception $e) {  
        echo "An unexpected error occurred fetching person data: " . $e->getMessage() . "\n";  
    }  
}
```

### **Fetch ...**

Check the API reference below to see all available nodes. 

### **Webhooks (Subscriptions)**

The client provides methods to manage subscriptions (webhooks).  
```
<?php  
// ... (initialization from above)

async function manageSubscriptions(SS12000Client $client) {  
    try {  
        echo "\nFetching subscriptions...\n";  
        $subscriptions = $client->getSubscriptions();  
        echo "Fetched subscriptions: " . json_encode($subscriptions, JSON_PRETTY_PRINT) . "\n";

        // Example: Create a subscription (requires a publicly accessible webhook URL)  
        // echo "\nCreating a subscription...\n";  
        // $newSubscription = $client->createSubscription([  
        //     'name' => 'My PHP Test Subscription',  
        //     'target' => 'http://your-public-webhook-url.com/ss12000-webhook', // Replace with your public URL  
        //     'resourceTypes' => [['resource' => 'Person'], ['resource' => 'Activity']]  
        // ]);  
        // echo "Created subscription: " . json_encode($newSubscription, JSON_PRETTY_PRINT) . "\n";

        // Example: Delete a subscription  
        // if (!empty($subscriptions\['data'])) {  
        //     $subToDeleteId = $subscriptions['data'][0]['id'];  
        //     echo "\nDeleting subscription with ID: {$subToDeleteId}...\n";  
        //     $client->deleteSubscription($subToDeleteId);  
        //     echo "Subscription deleted successfully.\n";  
        // }

    } catch (RequestException $e) {  
        echo "An HTTP request error occurred managing subscriptions: " . $e->getMessage() . "\n";  
    } catch (\\Exception $e) {  
        echo "An unexpected error occurred managing subscriptions: " . $e->getMessage() . "\n";  
    }  
}

```

## **API Reference**

The SS12000Client class is designed to expose methods for all SS12000 API endpoints. All methods return an associative array (decoded JSON) for data retrieval or void for operations without content (e.g., DELETE). Parameters are typically passed as associative arrays.  
Here is a list of the primary resource paths defined in the OpenAPI specification, along with their corresponding client methods:

* /organisations  
  * getOrganisations(array $queryParams)  
  * lookupOrganisations(array $body, bool $expandReferenceNames)  
  * getOrganisationById(string $orgId, bool $expandReferenceNames)  
* /persons  
  * getPersons(array $queryParams)  
  * lookupPersons(array $body, array $expand, bool $expandReferenceNames)  
  * getPersonById(string $personId, array $expand, bool $expandReferenceNames)  
* /placements  
  * getPlacements(array $queryParams)  
  * lookupPlacements(array $body, array $expand, bool $expandReferenceNames)  
  * getPlacementById(string $placementId, array $expand, bool $expandReferenceNames)  
* /duties  
  * getDuties(array $queryParams)  
  * lookupDuties(array $body, array $expand, bool $expandReferenceNames)  
  * getDutyById(string $dutyId, array $expand, bool $expandReferenceNames)  
* /groups  
  * getGroups(array $queryParams)  
  * lookupGroups(array $body, array $expand, bool $expandReferenceNames)  
  * getGroupById(string $groupId, array $expand, bool $expandReferenceNames)  
* /programmes  
  * getProgrammes(array $queryParams)  
  * lookupProgrammes(array $body, array $expand, bool $expandReferenceNames)  
  * getProgrammeById(string $programmeId, array $expand, bool $expandReferenceNames)  
* /studyplans  
  * getStudyPlans(array $queryParams)  
  * lookupStudyPlans(array $body, array $expand, bool $expandReferenceNames)  
  * getStudyPlanById(string $studyPlanId, array $expand, bool $expandReferenceNames)  
* /syllabuses  
  * getSyllabuses(array $queryParams)  
  * lookupSyllabuses(array $body, bool $expandReferenceNames)  
  * getSyllabusById(string $syllabusId, bool $expandReferenceNames)  
* /schoolUnitOfferings  
  * getSchoolUnitOfferings(array $queryParams)  
  * lookupSchoolUnitOfferings(array $body, array $expand, bool $expandReferenceNames)  
  * getSchoolUnitOfferingById(string $offeringId, array $expand, bool $expandReferenceNames)  
* /activities  
  * getActivities(array $queryParams)  
  * lookupActivities(array $body, array $expand, bool $expandReferenceNames)  
  * getActivityById(string $activityId, array $expand, bool $expandReferenceNames)  
* /calendarEvents  
  * getCalendarEvents(array $queryParams)  
  * lookupCalendarEvents(array $body, array $expand, bool $expandReferenceNames)  
  * getCalendarEventById(string $eventId, array $expand, bool $expandReferenceNames)  
* /attendances  
  * getAttendances(array $queryParams)  
  * lookupAttendances(array $body, array $expand, bool $expandReferenceNames)  
  * getAttendanceById(string $attendanceId, array $expand, bool $expandReferenceNames)  
  * deleteAttendance(string $attendanceId)  
* /attendanceEvents  
  * getAttendanceEvents(array $queryParams)  
  * lookupAttendanceEvents(array $body, array $expand, bool $expandReferenceNames)  
  * getAttendanceEventById(string $eventId, array $expand, bool $expandReferenceNames)  
* /attendanceSchedules  
  * getAttendanceSchedules(array $queryParams)  
  * lookupAttendanceSchedules(array $body, array $expand, bool $expandReferenceNames)  
  * getAttendanceScheduleById(string $scheduleId, array $expand, bool $expandReferenceNames)  
* /grades  
  * getGrades(array $queryParams)  
  * lookupGrades(array $body, array $expand, bool $expandReferenceNames)  
  * getGradeById(string $gradeId, array $expand, bool $expandReferenceNames)  
* /aggregatedAttendance  
  * getAggregatedAttendances(array $queryParams)  
  * lookupAggregatedAttendances(array $body, array $expand, bool $expandReferenceNames)  
  * getAggregatedAttendanceById(string $attendanceId, array $expand, bool $expandReferenceNames)  
* /resources  
  * getResources(array $queryParams)  
  * lookupResources(array $body, bool $expandReferenceNames)  
  * getResourceById(string $resourceId, bool $expandReferenceNames)  
* /rooms  
  * getRooms(array $queryParams)  
  * lookupRooms(array $body, bool $expandReferenceNames)  
  * getRoomById(string $roomId, bool $expandReferenceNames)  
* /subscriptions  
  * getSubscriptions(array $queryParams)  
  * createSubscription(array|object $body)  
  * deleteSubscription(string $subscriptionId)  
  * getSubscriptionById(string $subscriptionId)  
  * updateSubscription(string $subscriptionId, array|object $body)  
* /deletedEntities  
  * getDeletedEntities(array $queryParams)  
* /log  
  * getLog(array $queryParams)  
* /statistics  
  * getStatistics(array $queryParams)

Detailed information on available parameters can be found in the PHPDoc comments within src/SS12000Client.php.

The .yaml file can be downloaded from the SS12000 site over at [sis.se](https://www.sis.se/standardutveckling/tksidor/tk400499/sistk450/ss-12000/). 

## **Webhook Receiver (PHP Example)**

To receive webhooks in a PHP application, you would typically set up a dedicated endpoint. This example demonstrates a basic PHP script for receiving SS12000 notifications.  

This is just an example and is not part of the client library. It just shows how you could implement a receiver server for the webhooks. The code below is not production ready code, it's just a thought experiment that will point you in a direction toward a simple solution. 
```
<?php  
// webhook.php

// It's good practice to include Composer's autoloader if you're using other libraries  
// require_once __DIR__ . '/vendor/autoload.php';

// Log incoming request details  
error_log("Received a webhook from SS12000\!");  
error_log("Headers: " . json_encode(getallheaders(), JSON_PRETTY_PRINT));

$input = file_get_contents('php://input');  
error_log("Body: " . $input);

try {  
    $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

    // Implement your logic to handle the webhook message here.  
    // E.g., save the information to a database, trigger an update, etc.

    if (isset($data['modifiedEntites']) && is_array($data['modifiedEntites'])) {  
        foreach ($data['modifiedEntites'] as $resourceType) {  
            error\_log("Changes for resource type: {$resourceType}");  
            // You can instantiate SS12000Client here to fetch updated information  
            // For example:  
            // $client = new SS12000Client(BASE_URL, AUTH_TOKEN);  
            // if ($resourceType === 'Person') { $client->getPersons(['id' => $data['id_of_modified_entity']]); }  
        }  
    }

    if (isset($data['deletedEntities']) && is_array($data['deletedEntities'])) {  
        error_log("There are deleted entities to fetch from /deletedEntities.");  
        // Call client->getDeletedEntities(...) to fetch the deleted IDs.  
    }

    // Send a 200 OK response to acknowledge receipt  
    http_response_code(200);  
    echo json_encode(['message' => 'Webhook received successfully!']);

} catch (JsonException $e) {  
    error_log("Error parsing JSON webhook body: " . $e->getMessage());  
    http_response_code(400); // Bad Request  
    echo json_encode(\['error' => 'Invalid JSON body.'\]);  
} catch (Exception $e) {  
    error_log("Error processing webhook: " . $e->getMessage());  
    http_response_code(500); // Internal Server Error  
    echo json_encode(['error' => 'Internal server error.'\]);  
}

exit(); // Ensure no further output
```

To make this webhook endpoint accessible, you would typically configure your web server (Apache, Nginx) to route requests to webhook.php. Remember that for the SS12000 API to reach your webhook, it must be publicly accessible (e.g., through a reverse proxy or tunneling service like ngrok).

## **Contribute**

Contributions are welcome! If you want to add, improve, optimize or just change things just send in a pull request and I will have a look. Found a bug and don't know how to fix it? Create an issue!

## **License**

This project is licensed under the MIT License.