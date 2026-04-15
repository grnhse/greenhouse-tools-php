# Greenhouse Service Tools For PHP

**IMPORTANT NOTE**: Harvest V1/V2 will be going out of service on 8/31/2026. You should upgrade
to v3 as soon as possible. V3 includes support for the latest version of Guzzle and 
PHP 8.5.4, but drops support for older versions of PHP.

This package of tools is provided by Greenhouse for customers who use PHP.  There are four tools provided.

1. **Job Board Service**: Used to embed iframes in your template or view files.  
2. **Job API Service**: Used to fetch data from the Greenhouse Job Board API.
3. **Application Service**: Used to send applications in to Greenhouse.
4. **Harvest Service**: Used to interact with the Harvest API.

# Requirements
1. PHP Version 
    - 5.6 or greater for V1.
    - 7.3 or greater for V2.
    - 8.5 or greater for V3.
2. [Composer](https://getcomposer.org/).  You should be using Composer to manage this package. 

# Installing
This is available on Packagist.  Install via Composer.  Add the following to your requirements:

```
    "grnhse/greenhouse-tools-php": "~3.0"
```

# New In 3.0
- Upgrades Guzzle to 7.10
- Harvest Service updated to translate into V3 URL formats instead of V1 
  style formats. Some method names may have changed as a result.
- Harvest v3 URLs have been flattened to not include multiple IDs in the URLs. 

# New In 2.2
- Represents a minor version upgrade from Guzzle 7.1 to 7.5.
- Adds support for versions of Greenhouse endpoints beyond v1.

# New In 2.1
In order to support Greenhouse's new Inclusion URLs, we dropped a requirement that an $id be included for some URLs. Previously, a URL like `getActivityFeedForUser()` would throw a Greenhouse Exception with a message that id was required. Now, that method will just translate to `user/activity_feed` and return a 404 Not Found response. We did this in order to support Greenhouse's new Inclusion URLs. In the previous version `getQuestionsSetsForDemographics()`, [(found here)](https://developers.greenhouse.io/harvest.html#get-list-demographic-question-sets), would have thrown an "id required" exception instead of correctly routing to `demographics/question_sets`.

# New in 2.0
Support for PHP 5.6, 7.0, 7.1, and 7.2 were dropped in order to support the latest version of Guzzle.

# Greenhouse Service
The Greenhouse Service is a parent service that returns the other Greenhouse Services.  By using this service, you have access to all the other services.  The Greenhouse service takes an array that optionally includes your job board URL Token [(found here in Greenhouse)](https://app.greenhouse.io/configure/dev_center/config/) and your Job Board API Credentials [(found here in Greenhouse)](https://app.greenhouse.io/configure/dev_center/credentials).  Create a Greenhouse Service object like this:

```
<?php

use \Greenhouse\GreenhouseToolsPhp\GreenhouseService;
	
$greenhouseService = new GreenhouseService([
	'apiKey' => '<your_api_key>', 
	'boardToken' => '<your_board_token>'
]);

?>
```

Using this service, you can easily access the other Greenhouse Services and it will use the board token and client token as appropriate.

# The Job Board Service
This service generates the appropriate HTML tags for use with the Greenhouse iframe integration.  Use this service to generate either links to a Greenhouse-hosted job board or the appropriate tags for a Greenhouse iframe.  Access the job board service by calling:

```
<?php

$jobBoardService = $greenhouseService->getJobBoardService();

// Link to a Greenhouse hosted job board
$jobBoardService->linkToGreenhouseJobBoard();

// Link to a Greenhouse hosted job application
$jobBoardService->linkToGreenhouseJobApplication(12345, 'Apply to this job!', 'source_token');

// Embed a Greenhouse iframe in your page
$jobBoardService->embedGreenhouseJobBoard();

?>
```
# The Job API Service
Use this service to fetch public job board information from our job board API.  This service does not require an API key.  This is used to interact with the GET endpoints in the Greenhouse Job Board API.  These methods can be [found here](https://developers.greenhouse.io/job-board.html).  Access this service via:

```
$jobApiService = $greenhouseService->getJobApiService();
```

The methods in this service are named in relation to the endpoints, so to use the GET Offices endpoint, you'd call:

```
$jobApiService->getOffices();
```

And to get a specific office:

```
$jobApiService->getOffice($officeId);
```

The only additional parameters used in any case are for the "content" and "questions" parameters in the Jobs endpoint.  These are managed with boolean arguments that default to `false` in the `getJobs` and `getJob` methods.  To get all jobs with their content included, you'd call:

```
$jobApiService->getJobs(true);
```

while to get a job with its questions included, you'd call:

```
$jobApiService->getJob($jobId, true);
```
# The Application Service
Use this service to post Applications into Greenhouse.  Use of this Service requires a Job Board API key which can be generated in Greenhouse.  Example usage of this service follows:

```
<?php

$appService = $greenhouseService->getApplicationApiService();
$postParams = array(
	'id' => 82354,
	'first_name' => 'Johnny',
	'last_name' => 'Test',
	'email' => 'jt@example.com',
	'resume' => new \CURLFile('path/to/file.pdf', 'application/pdf', 'resume.pdf'),
	'question_12345' => 'The answer you seek',
	'question_123456' => array(12345, 23456, 34567)
);
$appService->postApplication($postParams);

?>
```

## Submitting Multiselect Answers 
The Application will handle generating an Authorization header based on your API key and posting the application as a multi-part form.  This parameter array follows the PHP convention except for the case of multiselect submission (submitting parameters with the same name).  While the PHP docs want users to submit multiple values like this:

```
'question_123456[0]' => 23456,
'question_123456[1]' => 12345,
'question_123456[2]' => 34567,
```

The Greenhouse package requires you to do it like this:

```
'question_123456' => array(23456,12345,34567),
```

This prevents issues that arise for systems that do not understand the array-indexed nomenclature preferred by Libcurl.

# The Harvest Service
Use this service to interact with the Harvest API in Greenhouse.  
Documentation for the Harvest API [can be found here.](https://harvestdocs.
greenhouse.io/reference/getting-started)  The purpose of this service is to 
make interactions with the Harvest API easier.  To create a Harvest Service 
object, you must supply an active credentials.  Note that these are different 
from Job Board API keys.

As of V3, Harvest requires Oauth credentials instead of a single API key. 
When creating a Greenhouse Service that will be using the Harvest service, 
you must provide your public key and secret key in the constructor instead 
of an API key.
```
<?php
  $greenhouseService = new GreenhouseService([
    'publicAuthKey' => '<your_public_key>',
    'secretAuthKey' => '<your_secret_key>'
  ]);
  $harvestService = $greenhouseService->getHarvestService();
?>
```

When making a request, the Harvest service will automatically reach out to 
the Greenhouse Auth service to get a Bearer token. The service will then use 
this token to make requests to the Harvest API. You may cache this token 
until the expiration time. The expiration is available at 
`$harvestService->getTokenExpiration()`. If you do not cache the token, the 
service will request a new token for every request made to Harvest.  This is not recommended.

Via the Harvest service, you can interact with any Harvest methods outlined 
in the Greenhouse Harvest docs.  Harvest URLs fit into the following patterns:

1. `https://harvest.greenhouse.io/v3/<object>`: This is the most common URL 
   format.  Different behaviors will occur dependent on the http method used.
   Methods such as `getApplications` and `postApplications` will fit this 
   format. The method name will be used to translate into the correct URL 
   and http method. 
2. `https://harvest.greenhouse.io/v3/<object>/<object_id>`: This format will 
   be used for PATCH and DELETE methods that require the resource ID being 
   changed. It may also be used to GET a single resource. If, for some 
   reason, the GET/id method is not available, a URL parameter named `ids` 
   can be used to filter resources by specific IDs. 
3. `https://harvest.greenhouse.io/v3/<object>/bulk`: If bulk processing is 
   enabled for an endpoint, you will include 'bulk' => true in the 
   parameters array to access it. For example 
   `$harvestService->deleteCustomFieldOptions(['bulk' => true]);` See Harvest 
   Documentation for body formatting.
4. `https://harvest.greenhouse.io/v3/<object>/<object_id>/<operation>`: In 
   harvest v3, this usually means you are performing an operation on a 
   subobject, for instance, replacing an approver in an approver group.
5. `https://harvest.greenhouse.io/v3/<object>/<operation>/bulk`: This is 
   used for some bulk operations. For instance, `/users/activate/bulk`.

Some method calls and URLs do not fit this format, but the methods were named as close to fitting that format as possible.  These include:
  * `patchAnonymizeCandidate`: [Anonymize a candidate.](https://harvestdocs.greenhouse.io/reference/patch_v3-candidates-id-anonymize)
  * `postHireApplication`: [Mark a candidate as hired.](https://harvestdocs.greenhouse.io/reference/post_v3-applications-id-hire)
  * `postMoveApplication`: [Move an application to any stage.](https://harvestdocs.greenhouse.io/reference/post_v3-applications-id-move)
  * `postRejectApplication`: [Reject an application](https://harvestdocs.greenhouse.io/reference/post_v3-applications-id-reject)
  * `postUnrejectApplication`: [Unreject an application](https://harvestdocs.greenhouse.io/reference/post_v3-applications-id-unreject)
  * `postMergeCandidates`: [Merge a duplicate candidate to a primary candidate.](https://harvestdocs.greenhouse.io/reference/post_v3-candidates-id-merge)
  * `postConvertProspectToCandidate`: [Convert a Prospect to a Candidate.](https://harvestdocs.greenhouse.io/reference/post_v3-applications-id-convert-to-candidate)
  * `postActivateUser`: [Enable a disabled user from accessing Greenhouse.](https://harvestdocs.greenhouse.io/reference/post_v3-users-id-activate)
  * `postDeactivateUser`: [Disable a user from accessing Greenhouse.](https://harvestdocs.greenhouse.io/reference/post_v3-users-id-deactivate)

You should use the parameters array to supply any URL parameters and headers required by the harvest methods.  For any items that require a JSON body, this will also be supplied in the parameter array.

The parameters array reserves the following inputs:
* **id**: This is used to designate a resource id that is required to fill 
  in a URL. For patch and delete operations, this is required to indicate 
  the resource being operated on. For get operations, this is optional and 
  used to only return a single resource. The ID parameter will usually take 
  precedence over any other parameters.
* **headers**: This is used to supply any additional headers required by the 
  endpoint.  You do not need to include an Authorization header, the service 
  will handle that for you.
* **bulk**: This is used to access bulk endpoints.  If 'bulk' is set to true,
  the service will construct a bulk endpoint. If the endpoint does not exist,
  the request will return a 404.
* **body**: This is used to supply any JSON body required by the endpoint.  Supply 
  this as a string.  You can use json_encode to generate this string from an 
  array or object.

Any other parameters in the array will be converted to query string 
parameters. You will want to provide any paging or filtering parameters in 
this way. 

Ex: [Moving an application](https://developers.greenhouse.io/harvest.html#move-an-application)
```
$parameters = array(
    'id' => $applicationId,
    'body' => '{"from_stage_id": 123, "to_stage_id": 234}'
);
$harvestService->moveApplication($parameters);
```

Any paging or filtering options that would normally be supplied as a GET 
query string should also be included in the parameters array. In Harvest V3, 
paging and filtering parameters are only included in the initial request. 
Subsequent requests use a cursor provided in the Link header. The Harvest 
Service will automatically parse the Link header and provide the next page 
link via the `nextLink()` method. When this method returns null, there are 
no more pages.

The parameters array is also used to supply any paging and filtering options 
that would normally be supplied as a GET query string.  Anything that is not 
in the `id`, `headers`, `bulk`, or `body` key will be assumed to be a URL 
parameter. 

For example, if you want to search for all the applications on 3 different 
jobs and return them 100 at a time, your initial call would look like this:

Ex: [Getting a page of applications](https://harvestdocs.greenhouse.io/reference/get_v3-applications)
```
$parameters = array(
    'per_page' => 100,
    'job_ids' => '12345,23456,34567'
);
$harvestService->getApplications($parameters);
// Will call https://harvest.greenhouse.io/v3/applications?per_page=100&job_ids=12345,23456,34567
```

To get additional pages, you would do something like this:
```
while ($harvestService->nextLink()) {
  $harvestService->getNextPage();
}
```

**A note on future development**: The Harvest package makes use of PHP's magic 
`__call` method.  This is to handle Greenhouse's Harvest API advancing past 
this package.  New endpoint URLs should work automatically.  If Greenhouse 
adds a GET `https://harvest.greenhouse.io/v3/widgets` endpoint, calling 
`$harvestService->getWidgets()` should be supported by this package. In the 
case of non-v3 endpoints going forward, the harvest service can be 
constructed with a 3rd parameter that specifies version.  For instance, if 
Greenhouse adds a v4 endpoint at `https://harvest.greenhouse.io/v4/widgets`, 
you can initialize the service with `new HarvestService($publicKey, $secretKey, 'v4')` and then call 
`$harvestService->getWidgets()` to access that endpoint.


# Exceptions
All exceptions raised by the Greenhouse Service library extend from `GreenhouseException`.  Catch this exception to catch anything thrown from this library.
