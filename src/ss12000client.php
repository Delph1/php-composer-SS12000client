<?php

namespace SS12000\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri; // For robust URI combining

/**
 * SS12000 API Client.
 *
 * This library provides functions to interact with the SS12000 API
 * based on the provided OpenAPI specification.
 * It includes basic HTTP calls and Bearer Token authentication handling.
 */
class SS12000Client
{
    private GuzzleClient $httpClient;
    private string $baseUrl;

    /**
     * Initializes a new instance of the SS12000Client class.
     *
     * @param string $baseUrl Base URL for the SS12000 API (e.g., "https://some.server.se/v2.0").
     * @param string|null $authToken JWT Bearer Token for authentication.
     * @param GuzzleClient|null $httpClient Optional custom GuzzleHttp\Client instance. If not provided, a new one will be created.
     */
    public function __construct(string $baseUrl, ?string $authToken = null, ?GuzzleClient $httpClient = null)
    {
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('Base URL is mandatory for SS12000Client.');
        }

        // Add HTTPS check for baseUrl
        if (!str_starts_with($baseUrl, 'https://')) {
            error_log('Warning: Base URL does not use HTTPS. All communication should occur over HTTPS ' .
                      'in production environments to ensure security.');
        }

        if (empty($authToken)) {
            error_log('Warning: Authentication token is missing. Calls may fail if the API requires authentication.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');

        $config = [
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30, // Add a timeout for requests
        ];

        if (!empty($authToken)) {
            $config['headers']['Authorization'] = 'Bearer ' . $authToken;
        }

        $this->httpClient = $httpClient ?? new GuzzleClient($config);
    }

    /**
     * Performs a generic HTTP request to the API.
     *
     * @param string $method HTTP method (GET, POST, DELETE, PATCH).
     * @param string $path API path (e.g., "/organisations").
     * @param array $queryParams Query parameters.
     * @param array|object|null $jsonContent JSON request body.
     * @return array|object Decoded JSON response.
     * @throws RequestException If the request fails.
     * @throws \JsonException If JSON decoding fails.
     */
    private function request(string $method, string $path, array $queryParams = [], $jsonContent = null)
    {
        $uri = Uri::withQueryValues(new Uri($this->baseUrl . $path), $queryParams);

        $options = [];
        if ($jsonContent !== null) {
            $options['json'] = $jsonContent;
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);

            if ($response->getStatusCode() === 204) { // No Content
                return [];
            }

            $jsonResponse = (string) $response->getBody();
            return json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            error_log("Error during {$method} call to {$uri}: " . $e->getMessage());
            if ($e->hasResponse()) {
                $errorContent = (string) $e->getResponse()->getBody();
                error_log("API Error Response: " . $errorContent);
            }
            throw $e;
        } catch (\JsonException $e) {
            error_log("JSON decoding error: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            error_log("An unexpected error occurred: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Performs a generic HTTP request to the API without expecting a return value.
     *
     * @param string $method HTTP method (DELETE).
     * @param string $path API path.
     * @param array $queryParams Query parameters.
     * @param array|object|null $jsonContent JSON request body.
     * @throws RequestException If the request fails.
     */
    private function requestNoContent(string $method, string $path, array $queryParams = [], $jsonContent = null): void
    {
        $uri = Uri::withQueryValues(new Uri($this->baseUrl . $path), $queryParams);

        $options = [];
        if ($jsonContent !== null) {
            $options['json'] = $jsonContent;
        }

        try {
            $this->httpClient->request($method, $uri, $options);
        } catch (RequestException $e) {
            error_log("Error during {$method} call to {$uri}: " . $e->getMessage());
            if ($e->hasResponse()) {
                $errorContent = (string) $e->getResponse()->getBody();
                error_log("API Error Response: " . $errorContent);
            }
            throw $e;
        } catch (\Exception $e) {
            error_log("An unexpected error occurred: " . $e->getMessage());
            throw $e;
        }
    }

    // --- Organisation Endpoints ---

    /**
     * Get a list of organizations.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of organizations.
     */
    public function getOrganisations(array $queryParams = [])
    {
        // Map PHP-style snake_case to API-style camelCase if necessary
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key); // Custom mapping function
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/organisations', $mappedParams);
    }

    /**
     * Get multiple organizations based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of organizations.
     */
    public function lookupOrganisations(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/organisations/lookup', $queryParams, $body);
    }

    /**
     * Get an organization by ID.
     *
     * @param string $orgId ID of the organization.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The organization object.
     */
    public function getOrganisationById(string $orgId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/organisations/{$orgId}", $queryParams);
    }

    // --- Person Endpoints ---

    /**
     * Get a list of persons.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of persons.
     */
    public function getPersons(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/persons', $mappedParams);
    }

    /**
     * Get multiple persons based on a list of IDs or civic numbers.
     *
     * @param array $body Request body with IDs or civic numbers.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of persons.
     */
    public function lookupPersons(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/persons/lookup', $queryParams, $body);
    }

    /**
     * Get a person by person ID.
     *
     * @param string $personId ID of the person.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The person object.
     */
    public function getPersonById(string $personId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/persons/{$personId}", $queryParams);
    }

    // --- Placements Endpoints ---

    /**
     * Get a list of placements.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of placements.
     */
    public function getPlacements(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/placements', $mappedParams);
    }

    /**
     * Get multiple placements based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of placements.
     */
    public function lookupPlacements(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/placements/lookup', $queryParams, $body);
    }

    /**
     * Get a placement by ID.
     *
     * @param string $placementId ID of the placement.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The placement object.
     */
    public function getPlacementById(string $placementId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/placements/{$placementId}", $queryParams);
    }

    // --- Duties Endpoints ---

    /**
     * Get a list of duties.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of duties.
     */
    public function getDuties(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/duties', $mappedParams);
    }

    /**
     * Get multiple duties based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of duties.
     */
    public function lookupDuties(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/duties/lookup', $queryParams, $body);
    }

    /**
     * Get a duty by ID.
     *
     * @param string $dutyId ID of the duty.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The duty object.
     */
    public function getDutyById(string $dutyId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/duties/{$dutyId}", $queryParams);
    }

    // --- Groups Endpoints ---

    /**
     * Get a list of groups.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of groups.
     */
    public function getGroups(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/groups', $mappedParams);
    }

    /**
     * Get multiple groups based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of groups.
     */
    public function lookupGroups(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/groups/lookup', $queryParams, $body);
    }

    /**
     * Get a group by ID.
     *
     * @param string $groupId ID of the group.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The group object.
     */
    public function getGroupById(string $groupId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/groups/{$groupId}", $queryParams);
    }

    // --- Programmes Endpoints ---

    /**
     * Get a list of programmes.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of programmes.
     */
    public function getProgrammes(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/programmes', $mappedParams);
    }

    /**
     * Get multiple programmes based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of programmes.
     */
    public function lookupProgrammes(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/programmes/lookup', $queryParams, $body);
    }

    /**
     * Get a programme by ID.
     *
     * @param string $programmeId ID of the programme.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The programme object.
     */
    public function getProgrammeById(string $programmeId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/programmes/{$programmeId}", $queryParams);
    }

    // --- StudyPlans Endpoints ---

    /**
     * Get a list of study plans.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of study plans.
     */
    public function getStudyPlans(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/studyplans', $mappedParams);
    }

    /**
     * Get multiple study plans based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of study plans.
     */
    public function lookupStudyPlans(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/studyplans/lookup', $queryParams, $body);
    }

    /**
     * Get a study plan by ID.
     *
     * @param string $studyPlanId ID of the study plan.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The study plan object.
     */
    public function getStudyPlanById(string $studyPlanId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/studyplans/{$studyPlanId}", $queryParams);
    }

    // --- Syllabuses Endpoints ---

    /**
     * Get a list of syllabuses.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of syllabuses.
     */
    public function getSyllabuses(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/syllabuses', $mappedParams);
    }

    /**
     * Get multiple syllabuses based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of syllabuses.
     */
    public function lookupSyllabuses(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/syllabuses/lookup', $queryParams, $body);
    }

    /**
     * Get a syllabus by ID.
     *
     * @param string $syllabusId ID of the syllabus.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The syllabus object.
     */
    public function getSyllabusById(string $syllabusId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/syllabuses/{$syllabusId}", $queryParams);
    }

    // --- SchoolUnitOfferings Endpoints ---

    /**
     * Get a list of school unit offerings.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of school unit offerings.
     */
    public function getSchoolUnitOfferings(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/schoolUnitOfferings', $mappedParams);
    }

    /**
     * Get multiple school unit offerings based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of school unit offerings.
     */
    public function lookupSchoolUnitOfferings(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/schoolUnitOfferings/lookup', $queryParams, $body);
    }

    /**
     * Get a school unit offering by ID.
     *
     * @param string $offeringId ID of the school unit offering.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The school unit offering object.
     */
    public function getSchoolUnitOfferingById(string $offeringId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/schoolUnitOfferings/{$offeringId}", $queryParams);
    }

    // --- Activities Endpoints ---

    /**
     * Get a list of activities.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of activities.
     */
    public function getActivities(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/activities', $mappedParams);
    }

    /**
     * Get multiple activities based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of activities.
     */
    public function lookupActivities(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/activities/lookup', $queryParams, $body);
    }

    /**
     * Get an activity by ID.
     *
     * @param string $activityId ID of the activity.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The activity object.
     */
    public function getActivityById(string $activityId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/activities/{$activityId}", $queryParams);
    }

    // --- CalendarEvents Endpoints ---

    /**
     * Get a list of calendar events.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of calendar events.
     */
    public function getCalendarEvents(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/calendarEvents', $mappedParams);
    }

    /**
     * Get multiple calendar events based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of calendar events.
     */
    public function lookupCalendarEvents(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/calendarEvents/lookup', $queryParams, $body);
    }

    /**
     * Get a calendar event by ID.
     *
     * @param string $eventId ID of the calendar event.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The calendar event object.
     */
    public function getCalendarEventById(string $eventId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/calendarEvents/{$eventId}", $queryParams);
    }

    // --- Attendances Endpoints ---

    /**
     * Get a list of attendances.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of attendances.
     */
    public function getAttendances(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/attendances', $mappedParams);
    }

    /**
     * Get multiple attendances based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of attendances.
     */
    public function lookupAttendances(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/attendances/lookup', $queryParams, $body);
    }

    /**
     * Get an attendance by ID.
     *
     * @param string $attendanceId ID of the attendance.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The attendance object.
     */
    public function getAttendanceById(string $attendanceId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/attendances/{$attendanceId}", $queryParams);
    }

    /**
     * Delete an attendance by ID.
     *
     * @param string $attendanceId ID of the attendance to delete.
     * @return void
     */
    public function deleteAttendance(string $attendanceId): void
    {
        $this->requestNoContent('DELETE', "/attendances/{$attendanceId}");
    }

    // --- AttendanceEvents Endpoints ---

    /**
     * Get a list of attendance events.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of attendance events.
     */
    public function getAttendanceEvents(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/attendanceEvents', $mappedParams);
    }

    /**
     * Get multiple attendance events based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of attendance events.
     */
    public function lookupAttendanceEvents(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/attendanceEvents/lookup', $queryParams, $body);
    }

    /**
     * Get an attendance event by ID.
     *
     * @param string $eventId ID of the attendance event.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The attendance event object.
     */
    public function getAttendanceEventById(string $eventId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/attendanceEvents/{$eventId}", $queryParams);
    }

    // --- AttendanceSchedules Endpoints ---

    /**
     * Get a list of attendance schedules.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of attendance schedules.
     */
    public function getAttendanceSchedules(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/attendanceSchedules', $mappedParams);
    }

    /**
     * Get multiple attendance schedules based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of attendance schedules.
     */
    public function lookupAttendanceSchedules(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/attendanceSchedules/lookup', $queryParams, $body);
    }

    /**
     * Get an attendance schedule by ID.
     *
     * @param string $scheduleId ID of the attendance schedule.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The attendance schedule object.
     */
    public function getAttendanceScheduleById(string $scheduleId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/attendanceSchedules/{$scheduleId}", $queryParams);
    }

    // --- Grades Endpoints ---

    /**
     * Get a list of grades.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of grades.
     */
    public function getGrades(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/grades', $mappedParams);
    }

    /**
     * Get multiple grades based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of grades.
     */
    public function lookupGrades(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/grades/lookup', $queryParams, $body);
    }

    /**
     * Get a grade by ID.
     *
     * @param string $gradeId ID of the grade.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The grade object.
     */
    public function getGradeById(string $gradeId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/grades/{$gradeId}", $queryParams);
    }

    // --- AggregatedAttendance Endpoints ---

    /**
     * Get a list of aggregated attendances.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of aggregated attendances.
     */
    public function getAggregatedAttendances(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/aggregatedAttendance', $mappedParams);
    }

    /**
     * Get multiple aggregated attendances based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of aggregated attendances.
     */
    public function lookupAggregatedAttendances(array $body, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/aggregatedAttendance/lookup', $queryParams, $body);
    }

    /**
     * Get an aggregated attendance by ID.
     *
     * @param string $attendanceId ID of the aggregated attendance.
     * @param array $expand Describes if expanded data should be fetched.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The aggregated attendance object.
     */
    public function getAggregatedAttendanceById(string $attendanceId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if (!empty($expand)) {
            $queryParams['expand'] = $expand;
        }
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/aggregatedAttendance/{$attendanceId}", $queryParams);
    }

    // --- Resources Endpoints ---

    /**
     * Get a list of resources.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of resources.
     */
    public function getResources(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/resources', $mappedParams);
    }

    /**
     * Get multiple resources based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of resources.
     */
    public function lookupResources(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/resources/lookup', $queryParams, $body);
    }

    /**
     * Get a resource by ID.
     *
     * @param string $resourceId ID of the resource.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The resource object.
     */
    public function getResourceById(string $resourceId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/resources/{$resourceId}", $queryParams);
    }

    // --- Rooms Endpoints ---

    /**
     * Get a list of rooms.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of rooms.
     */
    public function getRooms(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/rooms', $mappedParams);
    }

    /**
     * Get multiple rooms based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of rooms.
     */
    public function lookupRooms(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/rooms/lookup', $queryParams, $body);
    }

    /**
     * Get a room by ID.
     *
     * @param string $roomId ID of the room.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object The room object.
     */
    public function getRoomById(string $roomId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/rooms/{$roomId}", $queryParams);
    }

    // --- Subscriptions (Webhooks) Endpoints ---

    /**
     * Get a list of subscriptions.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of subscriptions.
     */
    public function getSubscriptions(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/subscriptions', $mappedParams);
    }

    /**
     * Create a subscription.
     *
     * @param array|object $body Request body with subscription details.
     * @return array|object The created subscription object.
     */
    public function createSubscription($body)
    {
        return $this->request('POST', '/subscriptions', [], $body);
    }

    /**
     * Delete a subscription.
     *
     * @param string $subscriptionId ID of the subscription to delete.
     * @return void
     */
    public function deleteSubscription(string $subscriptionId): void
    {
        $this->requestNoContent('DELETE', "/subscriptions/{$subscriptionId}");
    }

    /**
     * Get a subscription by ID.
     *
     * @param string $subscriptionId ID of the subscription.
     * @return array|object The subscription object.
     */
    public function getSubscriptionById(string $subscriptionId)
    {
        return $this->request('GET', "/subscriptions/{$subscriptionId}");
    }

    /**
     * Update the expire time of a subscription by ID.
     *
     * @param string $subscriptionId ID of the subscription to update.
     * @param array|object $body Request body with expiry timestamp.
     * @return array|object The updated subscription object.
     */
    public function updateSubscription(string $subscriptionId, $body)
    {
        return $this->request('PATCH', "/subscriptions/{$subscriptionId}", [], $body);
    }

    // --- DeletedEntities Endpoint ---

    /**
     * Get a list of deleted entities.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of deleted entities.
     */
    public function getDeletedEntities(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/deletedEntities', $mappedParams);
    }

    // --- Log Endpoint ---

    /**
     * Get a list of log entries.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of log entries.
     */
    public function getLog(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/log', $mappedParams);
    }

    // --- Statistics Endpoint ---

    /**
     * Get a list of statistics.
     *
     * @param array $queryParams Filter parameters.
     * @return array|object A list of statistics.
     */
    public function getStatistics(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->mapParamKey($key);
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/statistics', $mappedParams);
    }

    /**
     * Helper function to map snake_case parameters to camelCase for the API.
     * This is a simplified example and might need to be expanded based on actual API parameter naming conventions.
     *
     * @param string $key
     * @return string
     */
    private function mapParamKey(string $key): string
    {
        // Example mapping for common patterns like 'start_date_on_or_before' to 'startDate.onOrBefore'
        // This is a basic conversion; for complex cases, a more robust mapping might be needed.
        return preg_replace_callback('/_([a-z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $key);
    }
}

// --- Example Usage (for testing purposes, not part of the library itself) ---
/*
require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path as necessary

use SS12000\Client\SS12000Client;
use GuzzleHttp\Exception\RequestException;

// Replace with your actual test server URL and JWT token
const BASE_URL = "https://some.server.se/v2.0";
const AUTH_TOKEN = "YOUR_JWT_TOKEN_HERE";

$client = new SS12000Client(BASE_URL, AUTH_TOKEN);

async function runExample(SS12000Client $client) {
    try {
        // Example: Get Organizations
        echo "\nFetching organizations...\n";
        $organizations = $client->getOrganisations(['limit' => 2]);
        echo "Fetched organizations: " . json_encode($organizations, JSON_PRETTY_PRINT) . "\n";

        if (!empty($organizations['data'])) {
            $firstOrgId = $organizations['data'][0]['id'];
            echo "\nFetching organization with ID: {$firstOrgId}...\n";
            $orgById = $client->getOrganisationById($firstOrgId, true); // expandReferenceNames = true
            echo "Fetched organization by ID: " . json_encode($orgById, JSON_PRETTY_PRINT) . "\n";
        }

        // Example: Get Persons
        echo "\nFetching persons...\n";
        $persons = $client->getPersons(['limit' => 2, 'expand' => ['duties']]);
        echo "Fetched persons: " . json_encode($persons, JSON_PRETTY_PRINT) . "\n";

        if (!empty($persons['data'])) {
            $firstPersonId = $persons['data'][0]['id'];
            echo "\nFetching person with ID: {$firstPersonId}...\n";
            $personById = $client->getPersonById($firstPersonId, ['duties', 'responsibleFor'], true);
            echo "Fetched person by ID: " . json_encode($personById, JSON_PRETTY_PRINT) . "\n";
        }

        // Example: Manage Subscriptions (Webhooks)
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
        // if (!empty($subscriptions['data'])) {
        //     $subToDeleteId = $subscriptions['data'][0]['id'];
        //     echo "\nDeleting subscription with ID: {$subToDeleteId}...\n";
        //     $client->deleteSubscription($subToDeleteId);
        //     echo "Subscription deleted successfully.\n";
        // }

    } catch (RequestException $e) {
        echo "An HTTP request error occurred: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "An unexpected error occurred: " . $e->getMessage() . "\n";
    }
}

// To run the example, uncomment the following line and execute this script:
// runExample($client);
*/
