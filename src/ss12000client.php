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
     * Accepts filter parameters (pass as associative array). Supported keys:
     * - parent (array)                : Array of organisation IDs to filter.
     * - schoolUnitCode (array)        : Array of skolenhetskoder.
     * - organisationCode (array)      : Array of organisation codes.
     * - municipalityCode (string)     : Kommunkod.
     * - type (array|string)           : /components/schemas/OrganisationTypeEnum
     * - schoolTypes (array|string)    : /components/schemas/SchoolTypesEnum
     * - startDate.onOrBefore (string) : Date (RFC3339, e.g. "2016-10-15").
     * - startDate.onOrAfter (string)  : Date (RFC3339).
     * - endDate.onOrBefore (string)   : Date (RFC3339).
     * - endDate.onOrAfter (string)    : Date (RFC3339).
     * - meta.created.before (string)  : Date-time (RFC3339).
     * - meta.created.after (string)   : Date-time (RFC3339).
     * - meta.modified.before (string) : Date-time (RFC3339).
     * - meta.modified.after (string)  : Date-time (RFC3339).
     * - expandReferenceNames (bool)   : Return displayName for referenced objects.
     * - sortkey (string)              : ModifiedDesc, DisplayNameAsc
     * - limit (int)                   : Max results to return.
     * - pageToken (string)            : Pagination token (opaque).
     *
     * Example:
     *   $client->getOrganisations([
     *       'schoolUnitCode' => ['F2311'],
     *       'limit' => 50,
     *       'expandReferenceNames' => true
     *   ]);
     *
     * @param array $queryParams Filter parameters (see supported keys above).
     * @return array|object A list of organizations.
     */
    public function getOrganisations(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key; // Custom mapping function
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/organisations', $mappedParams);
    }

    /**
     * Lookup multiple organisations by IDs or identifiers.
     *
     * Request body: array/object according to API (e.g. { "ids": [...] } or plain id array).
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get an organisation by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $orgId
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Supported query keys:
     * - nameContains (array)                 : Array of strings to search in name.
     * - civicNo (string)                     : Personnummer filter.
     * - eduPersonPrincipalName (string)      : Filter by eduPersonPrincipalName.
     * - identifier.value (string)            : External identifier value.
     * - identifier.context (string)          : Context for external identifier.
     * - relationship.entity.type (string)    : Relation type (e.g. enrolment, duty, etc.).
     * - relationship.organisation (string)   : Organisation UUID for relation.
     * - relationship.startDate.onOrBefore    : Date (RFC3339).
     * - relationship.startDate.onOrAfter     : Date (RFC3339).
     * - relationship.endDate.onOrBefore      : Date (RFC3339).
     * - relationship.endDate.onOrAfter       : Date (RFC3339).
     * - meta.created.before                  : Date-time (RFC3339).
     * - meta.created.after                   : Date-time (RFC3339).
     * - meta.modified.before                 : Date-time (RFC3339).
     * - meta.modified.after                  : Date-time (RFC3339).
     * - expand (array)                       : duties, responsibleFor, placements, ownedPlacements, groupMemberships
     * - expandReferenceNames (bool)          : Return displayName for referenced objects.
     * - sortkey (string)                     : DisplayNameAsc, GivenNameDesc, GivenNameAsc, FamilyNameDesc, FamilyNameAsc, CivicNoAsc, CivicNoDesc, ModifiedDesc 
     * - limit (int)
     * - pageToken (string)
     *
     * Example:
     *   $client->getPersons([
     *       'nameContains' => ['Pa','gens'],
     *       'expand' => ['duties'],
     *       'limit' => 20
     *   ]);
     *
     * @param array $queryParams Filter parameters (see supported keys above).
     * @return array|object A list of persons.
     */

    public function getPersons(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/persons', $mappedParams);
    }

    /**
     * Lookup multiple persons by IDs or civic numbers.
     *
     * Request body: array/object per API (e.g. { "ids": [...] } or plain array).
     * Query parameters:
     * - expand (array)                  : duties, responsibleFor, placements, ownedPlacements, groupMemberships
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a person by id.
     *
     * Query parameters:
     * - expand (array)                  : duties, responsibleFor, placements, ownedPlacements, groupMemberships
     * - expandReferenceNames (bool)
     *
     * @param string $personId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Supported query keys:
     * - organisation (string)             : UUID of organisation (placedAt).
     * - group (string)                    : UUID of group.
     * - startDate.onOrBefore (string)     : Date (RFC3339).
     * - startDate.onOrAfter (string)      : Date (RFC3339).
     * - endDate.onOrBefore (string)       : Date (RFC3339).
     * - endDate.onOrAfter (string)        : Date (RFC3339).
     * - child (string)                    : UUID of child person.
     * - owner (string)                    : UUID of owner.
     * - meta.created.before               : Date-time (RFC3339).
     * - meta.created.after                : Date-time (RFC3339).
     * - meta.modified.after               : Date-time (RFC3339).
     * - meta.modified.before              : Date-time (RFC3339).
     * - expand (array)                    : child, owner
     * - expandReferenceNames (bool)
     * - sortkey (string)                  : StartDateAsc, StartDateDesc, EndDateAsc, EndDateDesc, ModifiedDesc
     * - pageToken (string)
     * - limit (int)
     *
     * @param array $queryParams Filter parameters (see supported keys above).
     * @return array|object A list of placements.
     */
    public function getPlacements(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/placements', $mappedParams);
    }

    /**
     * Lookup multiple placements by IDs.
     *
     * Request body: array/object per API (ids).
     * Query parameters:
     * - expand (array)                    : child, owner
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param array $expand                    
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a placement by id.
     *
     * Query parameters:
     * - expand (array)                    : child, owner
     * - expandReferenceNames (bool)
     *
     * @param string $placementId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Common query parameters:
     * - organisation (string)          : UUID
     * - dutyRole (string)              : /components/schemas/DutyRole
     * - person (string)                : UUID
     * - startDate.onOrBefore (string)  : Date (RFC3339)
     * - startDate.onOrAfter (string)   : Date (RFC3339)
     * - endDate.onOrBefore   (string)  : Date (RFC3339)
     * - endDate.onOrAfter    (string)  : Date (RFC3339)
     * - meta.created.before (string)   : Date-time (RFC3339)
     * - meta.created.after (string)    : Date-time (RFC3339)
     * - meta.modified.before (string)  : Date-time (RFC3339)
     * - meta.modified.after (string)   : Date-time (RFC3339)
     * - expand (array)                 : person
     * - expandReferenceNames (bool)
     * - sortkey (string)               : StartDateAsc, StartDateDesc, ModifiedDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getDuties(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/duties', $mappedParams);
    }

    /**
     * Lookup multiple duties by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expand (array|string)                 : person
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a duty by id.
     *
     * Query parameters:
     * - expand (array|string)                : person
     * - expandReferenceNames (bool)
     *
     * @param string $dutyId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Typical query parameters:
     * - groupType (array)              : /components/schemas/GroupTypeEnum
     * - schoolTypes (array)            : /components/schemas/SchoolTypesEnum
     * - organisation (array|string)    : UUID
     * - startDate.onOrBefore (string)  : Date (RFC3339)
     * - startDate.onOrAfter (string)   : Date (RFC3339)
     * - endDate.onOrBefore   (string)  : Date (RFC3339)
     * - endDate.onOrAfter    (string)  : Date (RFC3339)
     * - meta.created.before (string)   : Date-time (RFC3339)
     * - meta.created.after (string)    : Date-time (RFC3339)
     * - meta.modified.before (string)  : Date-time (RFC3339)
     * - meta.modified.after (string)   : Date-time (RFC3339)
     * - expand (array)                 : assignmentRoles
     * - expandReferenceNames (bool)
     * - sortkey (string)               : ModifiedDesc, DisplayNameAsc, StartDateAsc, StartDateDesc, EndDateAsc, EndDateDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getGroups(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/groups', $mappedParams);
    }

    /**
     * Lookup multiple groups by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expand (array)                  : assignmentRoles
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a group by id.
     *
     * Query parameters:
     * - expand (array)                : assignmentRoles
     * - expandReferenceNames (bool)
     *
     * @param string $groupId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Common query parameters:
     * - schoolType (array)                     : /components/schemas/SchoolTypesEnum
     * - code (string)
     * - parentProgramme (string)
     * - meta.created.after (string)            : Date-time (RFC3339)
     * - meta.created.before (string)           : Date-time (RFC3339)
     * - meta.modified.before (string)          : Date-time (RFC3339)
     * - meta.modified.after (string)           : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)                       : NameAsc, CodeAsc, ModifiedDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getProgrammes(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/programmes', $mappedParams);
    }

    /**
     * Lookup multiple programmes by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupProgrammes(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/programmes/lookup', $queryParams, $body);
    }

    /**
     * Get a programme by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $programmeId
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function getProgrammeById(string $programmeId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/programmes/{$programmeId}", $queryParams);
    }

    // --- StudyPlans Endpoints ---

    /**
     * Get a list of study plans.
     *
     * Common filters:
     * - student (array|string)
     * - startDate.onOrAfter (string)   : Date (RFC3339)
     * - startDate.onOrBefore(string)   : Date (RFC3339)
     * - endDate.onOrAfter (string)     : Date (RFC3339)
     * - endDate.onOrBefore (string)    : Date (RFC3339)
     * - meta.created.after (string)    : Date-time (RFC3339)
     * - meta.created.before (string,   : Date-time (RFC3339)
     * - meta.modified.before (string)  : Date-time (RFC3339)
     * - meta.modified.after (string)   : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)               : ModifiedDesc, StartDateAsc, StartDateDesc, EndDateAsc, EndDateDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getStudyPlans(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/studyplans', $mappedParams);
    }

    /**
     * Lookup of studyplans is not part of the SS12000 standard.
     * 
     * Get multiple study plans based on a list of IDs.
     *
     * @param array $body Request body with IDs.
     * @param bool $expandReferenceNames Return `displayName` for all referenced objects.
     * @return array|object A list of study plans.
     * public function lookupStudyPlans(array $body, array $expand = [], bool $expandReferenceNames = false)
     * {
     *   $queryParams = [];
     *   if ($expandReferenceNames) {
     *       $queryParams['expandReferenceNames'] = true;
     *   }
     *   return $this->request('POST', '/studyplans/lookup', $queryParams, $body);
     * }
     */

    /**
     * Get a study plan by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $studyPlanId
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function getStudyPlanById(string $studyPlanId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/studyplans/{$studyPlanId}", $queryParams);
    }

    // --- Syllabuses Endpoints ---

    /**
     * Get a list of syllabuses.
     *
     * Common query parameters:
     * - meta.created.before (string)   : Date-time (RFC3339)
     * - meta.created.after (string)    : Date-time (RFC3339)
     * - meta.modified.before (string)  : Date-time (RFC3339)
     * - meta.modified.after (string)   : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)               : ModifiedDesc, SubjectNameAsc, SubjectNameDesc, SubjectCodeAsc, SubjectCodeDesc, CourseNameAsc, CourseNameDesc, CourseCodeAsc, CourseCodeDesc, SubjectDesignationAsc, SubjectDesignationDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getSyllabuses(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/syllabuses', $mappedParams);
    }

    /**
     * Lookup multiple syllabuses by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a syllabus by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $syllabusId
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Common filters:
     * - organisation (string, UUID)
     * - meta.created.after (string)      : Date-time (RFC3339)
     * - meta.created.before (string)     : Date-time (RFC3339)
     * - meta.modified.after (string)     : Date-time (RFC3339)
     * - meta.modified.before (string)    : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)                 : ModifiedDesc, StartDateAsc, StartDateDesc, EndDateAsc, EndDateDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getSchoolUnitOfferings(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/schoolUnitOfferings', $mappedParams);
    }

    /**
     * Lookup multiple school unit offerings by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupSchoolUnitOfferings(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/schoolUnitOfferings/lookup', $queryParams, $body);
    }

    /**
     * Get a school unit offering by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $offeringId
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function getSchoolUnitOfferingById(string $offeringId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/schoolUnitOfferings/{$offeringId}", $queryParams);
    }

    // --- Activities Endpoints ---

/**
 * Get a list of activities.
 *
 * Common filters:
 * - member (string)                        : UUID
 * - teacher (string)                       : UUID
 * - organisation (string)                  : UUID
 * - group (string)                         : UUID
 * - startDate.onOrAfter (string)           : Date (RFC3339)
 * - startDate.onOrBefore (string)          : Date (RFC3339)
 * - endDate.onOrAfter (string)             : Date (RFC3339)
 * - endDate.onOrBefore (string)            : Date (RFC3339)
 * - meta.created.after (string)            : Date-time (RFC3339)
 * - meta.created.before (string)           : Date-time (RFC3339)
 * - meta.modified.after (string)           : Date-time (RFC3339)
 * - meta.modified.before (string)          : Date-time (RFC3339)
 * - expand (array)                         : groups, teachers, syllabus
 * - expandReferenceNames (bool)
 * - sortkey (string)                       : ModifiedDesc, DisplayNameAsc
 * - limit (int)
 * - pageToken (string)
 *
 * @param array $queryParams
 * @return array|object
 */
    public function getActivities(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/activities', $mappedParams);
    }

    /**
     * Lookup multiple activities by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expand (array)                         : groups, teachers, syllabus
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get an activity by id.
     *
     * Query parameters:
     * - expand (array)                         : groups, teachers, syllabus
     * - expandReferenceNames (bool)
     *
     * @param string $activityId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Required/important query keys (pass as associative array):
     * - startTime.onOrAfter (string) : Date-time (RFC3339) — required by spec for some calls.
     * - startTime.onOrBefore (string): Date-time (RFC3339) — required by spec for some calls.
     * - endTime.onOrBefore (string)  : Date-time (RFC3339).
     * - endTime.onOrAfter (string)   : Date-time (RFC3339).
     * - activity (string)            : Activity UUID.
     * - student (string)             : Person UUID (student membership filter).
     * - teacher (string)             : Duty UUID (teacher filter).
     * - organisation (string)        : Organisation UUID.
     * - group (string)               : Group UUID.
     * - meta.created.before (string) : Date-time (RFC3339).
     * - meta.created.after (string)  : Date-time (RFC3339).
     * - meta.modified.before (string): Date-time (RFC3339).
     * - meta.modified.after (string) : Date-time (RFC3339).
     * - expand (array)               : activity, attendance
     * - expandReferenceNames (bool)
     * - sortkey (string)             : ModifiedDesc, StartTineAsc, StartTimeDesc
     * - limit (int)
     * - pageToken (string)
     *
     * Example:
     *   $client->getCalendarEvents([
     *       'startTime.onOrAfter' => '2025-08-01T00:00:00+02:00',
     *       'startTime.onOrBefore' => '2025-08-31T23:59:59+02:00',
     *       'limit' => 200
     *   ]);
     *
     * @param array $queryParams Filter parameters (see supported keys above).
     * @return array|object A list of calendar events.
     */
    public function getCalendarEvents(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/calendarEvents', $mappedParams);
    }

    /**
     * Lookup multiple calendar events by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupCalendarEvents(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/calendarEvents/lookup', $queryParams, $body);
    }

    /**
     * Get a calendar event by id.
     *
     * Query parameters:
     * - expand (array)              : activity, attendance
     * - expandReferenceNames (bool)
     *
     * @param string $eventId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Supported query keys:
     * - student (string)               : Student (person) UUID.
     * - organisation (string)          : Organisation UUID.
     * - calendarEvent (string)         : CalendarEvent UUID.
     * - meta.created.before            : Date-time (RFC3339).
     * - meta.created.after             : Date-time (RFC3339).
     * - meta.modified.before           : Date-time (RFC3339).
     * - meta.modified.after            : Date-time (RFC3339).
     * - expandReferenceNames (bool)
     * - limit (int)
     * - pageToken (string)
     *
     * Example:
     *   $client->getAttendances(['student' => '046b6c7f-...','limit' => 50]);
     *
     * @param array $queryParams Filter parameters (see supported keys above).
     * @return array|object A list of attendances.
     */
    public function getAttendances(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/attendances', $mappedParams);
    }

    /**
     * Post a new attendance.
     * 
     * Request body: object with attendance data. : /components/schemas/Attendance
     * @param array $body
     * @return void
     */
    public function postAttendance(array $body): void
    {
        $this->requestNoContent('POST', '/attendances', [], $body);
    }

    /**
     * Lookup multiple attendances by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupAttendances(array $body, bool $expandReferenceNames = false)
    {
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/attendances/lookup', $queryParams, $body);
    }

    /**
     * Get an attendance by id.
     *
     * @param string $attendanceId
     * @return array|object
     */
    public function getAttendanceById(string $attendanceId)
    {
        return $this->request('GET', "/attendances/{$attendanceId}");
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
     * Common query parameters:
     * - group (array|string)
     * - person (string, UUID)
     * - meta.created.before (string)           : Date-time (RFC3339)
     * - meta.created.after (string)            : Date-time (RFC3339)
     * - meta.modified.before (string)          : Date-time (RFC3339)
     * - meta.modified.after (string)           : Date-time (RFC3339)
     * - expand (array)                         : person, group, registeredBy
     * - expandReferenceNames (bool)
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getAttendanceEvents(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/attendanceEvents', $mappedParams);
    }

    /**
     * Post a new attendance event.
     * 
     * Request body: object with attendance event data. : /components/schemas/AttendanceEvent
     * @param array $body
     * return void
     */
    public function postAttendanceEvent(array $body): void
    {
        $this->requestNoContent('POST', '/attendanceEvents', [], $body);
    }

    /**
     * Lookup multiple attendance events by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expand (array)                       : person, group, registeredBy
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get an attendance event by id.
     *
     * Query parameters:
     * - expand (array)                        : person, group, registeredBy
     * - expandReferenceNames (bool)
     *
     * @param string $eventId
     * @param array $expand
     * @param bool $expandReferenceNames
     * @return array|object
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

    /**
     * Delete an attendance event by ID.
     * 
     * @param string $eventId ID of the attendance event to delete.
     * @return void
     */
    public function deleteAttendanceEvent(string $eventId): void
    {
        $this->requestNoContent('DELETE', "/attendanceEvents/{$eventId}");
    }

    // --- AttendanceSchedules Endpoints ---

    /**
     * Get a list of attendance schedules.
     *
     * Common filters:
     * - placement (string, UUID)
     * - group (string, UUID)
     * - startDate.onOrBefore (string)      : Date (RFC3339)
     * - startDate.onOrAfter (string)       : Date (RFC3339)
     * - endDate.onOrBefore (string)        : Date (RFC3339)
     * - endDate.onOrAfter (string)         : Date (RFC3339)
     * - meta.created.before (string)       : Date-time (RFC3339)
     * - meta.created.after (string)        : Date-time (RFC3339)
     * - meta.modified.before (string)      : Date-time (RFC3339)
     * - meta.modified.after (string)       : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getAttendanceSchedules(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/attendanceSchedules', $mappedParams);
    }

    /**
     * Post a new attendance schedule.
     * 
     * Request body: object with attendance schedule data. : /components/schemas/AttendanceSchedule
     * @param array $body
     * return void
     */
    public function postAttendanceSchedule(array $body): void
    {
        $this->requestNoContent('POST', '/attendanceSchedules', [], $body);
    }

    /**
     * Lookup multiple attendance schedules by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupAttendanceSchedules(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/attendanceSchedules/lookup', $queryParams, $body);
    }

    /**
     * Get an attendance schedule by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $scheduleId
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function getAttendanceScheduleById(string $scheduleId, array $expand = [], bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/attendanceSchedules/{$scheduleId}", $queryParams);
    }

    /**
     * Delete an attendance schedule by ID.
     * 
     * @param string $scheduleId ID of the attendance schedule to delete.
     * @return void
     */
    public function deleteAttendanceSchedule(string $scheduleId): void
    {
        $this->requestNoContent('DELETE', "/attendanceSchedules/{$scheduleId}");
    }

    // --- Grades Endpoints ---

    /**
     * Get a list of grades.
     *
     * Common query parameters:
     * - organisation (string)              : UUID
     * - student (string)                   : UUID
     * - registeredBy (string)              : UUID
     * - gradingTeacher (string             : UUID
     * - registeredDate.onOrAfter (string)  : Date (RFC3339)
     * - registeredDate.onOrBefore (string) : Date (RFC3339)
     * - meta.created.before (string)       : Date-time (RFC3339)
     * - meta.created.after (string)        : Date-time (RFC3339)
     * - meta.modified.before (string)      : Date-time (RFC3339)
     * - meta.modified.after (string)       : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)                   : registeredDateAsc, registeredDateDesc, ModifiedDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getGrades(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/grades', $mappedParams);
    }

    /**
     * Lookup multiple grades by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupGrades(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/grades/lookup', $queryParams, $body);
    }

    /**
     * Get a grade by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $gradeId
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function getGradeById(string $gradeId, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('GET', "/grades/{$gradeId}", $queryParams);
    }

    // --- Absences Endpoints ---

    /**
     * Get a list of absences.
     * Common query parameters:
     * - student (string)                   : UUID
     * - organisation (string)              : UUID
     * - registeredBy (string)              : UUID
     * - type (string)                      : Absence type UUID
     * - startTime.onOrAfter (string)       : Date-time (RFC3339)
     * - startTime.onOrBefore (string)      : Date-time (RFC3339)
     * - endTime.onOrAfter (string)         : Date-time (RFC3339)
     * - endTime.onOrBefore (string)        : Date-time (RFC3339)
     * - meta.created.before (string)       : Date-time (RFC3339)
     * - meta.created.after (string)        : Date-time (RFC3339)
     * - meta.modified.before (string)      : Date-time (RFC3339)
     * - meta.modified.after (string)       : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)                   : ModifiedDesc, StartTimeAsc, StartTimeDesc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams Filter parameters (see supported keys above).
     * @return array|object A list of absences.
     */
    public function getAbsences(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/absences', $mappedParams);
    }
    
    /** 
     * Post a new absence.
     * 
     * Request body: object with absence data. : /components/schemas/Absence
     * @param array $body
     * return void
     */
    public function postAbsence(array $body): void
    {
        $this->requestNoContent('POST', '/absences', [], $body);
    }

    /**
     * Lookup multiple absences by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
     */
    public function lookupAbsences(array $body, bool $expandReferenceNames = false)
    {
        $queryParams = [];
        if ($expandReferenceNames) {
            $queryParams['expandReferenceNames'] = true;
        }
        return $this->request('POST', '/absences/lookup', $queryParams, $body);
    }

    /**
     * Get an absence by id.
     *
     * @param string $absenceId
     * @return array|object
     */
    public function getAbsenceById(string $absenceId)
    {
        return $this->request('GET', "/absences/{$absenceId}");
    }

    // --- AggregatedAttendance Endpoints ---

    /**
     * Get a list of aggregated attendances.
     *
     * Common query parameters:
     * - startDate                          : Date (RFC3339)
     * - endDate                            : Date (RFC3339)
     * - organisation (string)              : UUID
     * - schoolType (array|string)          : /components/schemas/SchoolTypesEnum
     * - student (string)                   : UUID
     * - expand (array)                     : activity, student
     * - expandReferenceNames (bool)
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getAggregatedAttendances(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/aggregatedAttendance', $mappedParams);
    }

    /// lookup and individual {id} not part of the SS12000 standard

    // --- Resources Endpoints ---

    /**
     * Get a list of resources.
     *
     * Common filters:
     * - organisation (string)              : UUID
     * - meta.created.before (string)       : Date-time (RFC3339)           
     * - meta.created.after (string)        : Date-time (RFC3339)
     * - meta.modified.before (string)      : Date-time (RFC3339)
     * - meta.modified.after (string)       : Date-time (RFC3339)
     * - expandReferenceNames (bool)
     * - sortkey (string)                   : ModifiedDesc, DisplayNameAsc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getResources(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/resources', $mappedParams);
    }

    /**
     * Lookup multiple resources by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a resource by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $resourceId
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Common filters:
     * - owner/organisation (string)            : UUID
     * - meta.created.before (string)           : Date-time RFC3339
     * - meta.created.after (string)            : Date-time RFC3339
     * - meta.modified.before (string)          : Date-time RFC3339
     * - meta.modified.after (string)           : Date-time RFC3339
     * - expandReferenceNames (bool)
     * - sortkey (string)                       : ModifiedDesc, DisplayNameAsc
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getRooms(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/rooms', $mappedParams);
    }

    /**
     * Lookup multiple rooms by IDs.
     *
     * Request body: array/object with ids.
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param array $body
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a room by id.
     *
     * Query parameters:
     * - expandReferenceNames (bool)
     *
     * @param string $roomId
     * @param bool $expandReferenceNames
     * @return array|object
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
     * Get a list of subscriptions (webhooks).
     *
     * Supported query parameters:
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getSubscriptions(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/subscriptions', $mappedParams);
    }

    /**
     * Create a subscription.
     *
     * Request body: object with at least:
     * - name (string)
     * - target (string, publicly reachable webhook URL)
     * - resourceTypes (array)
     * - expires (string, date-time RFC3339) optional
     *
     * Example:
     *   $client->createSubscription(['name'=>'sub','target'=>'https://...', 'resourceTypes'=>[['resource'=>'Person']]]);
     *
     * @param array|object $body
     * @return array|object
     */
    public function createSubscription($body)
    {
        return $this->request('POST', '/subscriptions', [], $body);
    }

    /**
     * Delete a subscription by id.
     *
     * @param string $subscriptionId
     * @return void
     */
    public function deleteSubscription(string $subscriptionId): void
    {
        $this->requestNoContent('DELETE', "/subscriptions/{$subscriptionId}");
    }

    /**
     * Get a subscription by id.
     *
     * @param string $subscriptionId
     * @return array|object
     */
    public function getSubscriptionById(string $subscriptionId)
    {
        return $this->request('GET', "/subscriptions/{$subscriptionId}");
    }

    /**
     * Update a subscription (typically to extend expires).
     *
     * Request body: object with fields to update (e.g. ['expires' => '2026-01-01T00:00:00+00:00'])
     *
     * @param string $subscriptionId
     * @param array|object $body
     * @return array|object
     */
    public function updateSubscription(string $subscriptionId, $body)
    {
        return $this->request('PATCH', "/subscriptions/{$subscriptionId}", [], $body);
    }

    // --- DeletedEntities Endpoint ---

    /**
     * Get a list of deleted entities.
     *
     * Common query parameters:
     * - modifiedAfter / deletedAfter (string, date-time RFC3339)
     * - resourceTypes (array)
     * - limit (int)
     * - pageToken (string)
     *
     * @param array $queryParams
     * @return array|object
     */
    public function getDeletedEntities(array $queryParams = [])
    {
        $mappedParams = [];
        foreach ($queryParams as $key => $value) {
            $mappedKey = $this->$key;
            $mappedParams[$mappedKey] = $value;
        }
        return $this->request('GET', '/deletedEntities', $mappedParams);
    }

    // --- Log Endpoint ---

   /**
    * Create a log entry.
    * @param array|object $body Log entry data. : /components/schemas/LogEntry
    * @return void
    */
    public function createLogEntry($body)
    {
        $this->requestNoContent('POST', '/log', [], $body);
    }   

    // --- Statistics Endpoint ---

  /**
   * Create a statistics entry.
   * @param array|object $body Statistics entry data. : /components/schemas/StatisticsEntry
   * @return void
   */
    public function createStatisticsEntry($body)
    {
        $this->requestNoContent('POST', '/statistics', [], $body);
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
