<?php

ini_set('max_execution_time', 300); //300 seconds = 5 minutes
ini_set('memory_limit', '-1'); // unlimited memory
header("Access-Control-Allow-Origin: *");
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$data = getData();

switch ($data["command"]) {
	case 'badge_image':
		showBadgeImage($data);
		break;
	case 'badge_json':
		showBadgeJson($data);
		break;
	case 'badge_markdown':
		showBadgeMarkdown($data);
		break;
	case 'pull_request':
		createPullRequestComplete($data);
	default:
		showMaturity($data);
		break;
}

function showBadgeImage($data)
{
	$image_url = $data["maturity"]["badge"];
	$image = file_get_contents($image_url); 

	header("Content-Type: image/svg+xml;charset=utf-8");
	echo $image; 
	exit;
}

function showBadgeJson($data)
{
	$data["maturity"]["title"] = $data["title"];
	$data["maturity"]["version_json"] = $data["maturity_data"]["version_json"];
	
	header("Content-Type: application/json");
	echo json_encode($data["maturity"]); 
	exit;
}

function showBadgeMarkdown($data) 
{
	echo getBadgeMarkdown($data);
	exit;
}

function getBadgeMarkdown($data)
{
	$label = $data["maturity"]["label"];
	$level = $data["maturity"]["level"];
	$image_url = $data["maturity"]["badge"];
	$current_url = getCurrentUrl("details");

	return "[![$label]($current_url&command=badge_image)]($current_url)"; 
}

function showMaturity($data) 
{
	$data["badge_markdown"] = getBadgeMarkdown($data);
	header("Content-Type: application/json");
	echo json_encode($data); 
	exit;
}

function getData()
{
	$data["command"] = getQueryProperty("command");
	$projects = getQueryProperty("projects");
	$project = getQueryProperty("project");
	$token = getQueryProperty("token");
	$json =  getQueryProperty("json");
	$codes =  getQueryProperty("codes");
	$title = getQueryProperty("title");
	
	$new_codes = getQueryProperty("new_codes");
	$new_ignore = getQueryProperty("new_ignore");
	$new_version_json = getQueryProperty("new_version_json");

	$branch = getQueryProperty("branch");
	$tag = getQueryProperty("tag");

	$data["type"] = "undefined";
	if ($project != null) 
	{
		$data["type"] = "project";
		$data["tag"] = isset($tag) ? $tag : "happy";

		$data["new_version_json"] = $new_version_json;
		$data["new_ignore"] = $new_ignore;

		$data["token"] = $token;
		$data["project"] = $project;
		$data["title"] = $project;
		$data["branch"] = isset($branch) ? $branch : "master";
		$data["new_codes"] = explode("|", $new_codes);
		$data["maturity_data"] = getProjectMaturityData($project, $token);
		$data["maturity_model"] = getMaturityModel($data["maturity_data"]["version_json"]);
		$data["maturity"] = processMaturity($data);
	} 

	else if ($projects != null) 
	{
		$data["type"] = "projects";
		$data["token"] = $token;
		$data["projects"] = explode("|", $projects);
		$data["title"] = $projects;
		$temp = getProjectsMaturityData($data["projects"], $token);	
		$data["maturity_data"] = $temp["maturity_data"] ;
		$data["maturity_model"] = $temp["maturity_model"];
		$data["maturity"] = $temp["maturity"];
	}

	else if ($json != null) 
	{
		$data["type"] = "json";
		$data["codes"] = $codes;
		$data["title"] = ($title != null) ? $title : "undefined";
		$data["maturity_data"]["version_json"] = $json;
		$data["maturity_data"]["codes"] = explode("|", $codes);	
		$data["maturity_model"] = getMaturityModel($data["maturity_data"]["version_json"]);
		$data["maturity"] = processMaturity($data);
	}

	
	$data["title"] = $data["type"] . ":" . $data["title"];

	return $data;
}

function getProjectMaturityData($project, $token) 
{
	$url = "https://api.github.com/repos/" . $project . "/contents/maturity.json";

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Authorization: token ' . $token,
	    'Accept: application/vnd.github.v3.raw'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	if ($code == 200) {
		return json_decode($result, true);
	}

	return null;
}

function getProjectsMaturityData($projects, $token) 
{
	$maturity_data_arr = [];
	
	foreach($projects as &$project)
	{
		$temp["maturity_data"] =  getProjectMaturityData($project, $token);
		
		$ignored = false;

		if ($temp["maturity_data"] == null)
		{
			$ignored = true;
		}
		else if (array_key_exists("ignore", $temp["maturity_data"]))
		{
			$ignored = $temp["maturity_data"]["ignore"];
		}

		if ($ignored == false)	
		{
			$temp["maturity_model"] = getMaturityModel($temp["maturity_data"]["version_json"]);
			$temp["maturity"] = processMaturity($temp);

			if (array_key_exists("version_json", $temp["maturity_data"]) && $ignored !== true)
			{
				array_push($maturity_data_arr, $temp);
			}
		}
	}

	$maturity = $maturity_data_arr[0]; 	

	foreach ($maturity_data_arr as &$item)
	{
		if (isset($item["maturity"]) && !isset($maturity["maturity"]))
		{
			$maturity = $item;	
		}
		
		if (isset($item["maturity"]) && $item["maturity"]["level"] < $maturity["maturity"]["level"])
		{
			$maturity = $item;
		}
	}

	if (isset($maturity["maturity"]) == false)
	{
		$maturity["maturity"]["version_json"] = "none";
	}

	$maturity["projects_maturity"] = $maturity_data_arr;

	return $maturity;
}

function processMaturity($data) 
{
	if ($data["maturity_model"] == null)
	{
		return emptyMaturityModel();
	}

	$codes = [];
	if ($data["maturity_data"] != null)
	{
		$codes = $data["maturity_data"]["codes"];
	}

	$result["color"] = $data["maturity_model"]["empty_color"];
	$result["level"] = $data["maturity_model"]["empty_level"];
	$result["label"] = $data["maturity_model"]["badge_name"];
	
	$mustBreak = false;
	$levels = $data["maturity_model"]["levels"];
	
	for ($i=0; $i < count($levels); $i++) 
	{
		$items = $data["maturity_model"]["levels"][$i]["items"];
	
		for ($j=0; $j < count($items); $j++) 
		{
			if (in_array($items[$j]["code"], $codes) == false)
			{
				$mustBreak = true;
			}
			
			if ($mustBreak) break;
		}
		
		if ($mustBreak) break;
		
		$result["level"] = $levels[$i]["level"];
		$result["color"] = $levels[$i]["color"];
	}
	
	$result["badge"] = getBadgeUrl($result);
	
	return $result;
}

function emptyMaturityModel()
{
	$result["color"] = "red";
	$result["level"] = "nda";
	$result["label"] = "invalid maturity model";
	$result["badge"] = getBadgeUrl($result);

	return $result;	
}

function getBadgeUrl($result)
{
	$image_url = "https://img.shields.io/badge/" 
		. rawurlencode($result["label"]) . "-" . $result["level"] . "-" . $result["color"] . ".svg";

	return $image_url;
}

function getMaturityModel($version_url) 
{
	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $version_url);

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	curl_close($ch);

	return json_decode($result, true);
}

function getQueryProperty(string $name) 
{
	if (isset($_GET[$name])) 
	{
		return $_GET[$name];
	}

	return null;
}

function getCurrentUrl($command)
{
    if(isset($_SERVER['HTTPS'])){
        $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    }
    else{
        $protocol = 'http';
    }

    $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $url = preg_replace('/command=\w*/', "", $url);
    $url = str_replace("?&", "?", $url);

    return $url;
}

function createPullRequestComplete($data) 
{
	
	$path = "maturity.json";

	if ($data["project"] == null) {
    	returnBadRequest("this operation is only allowed for single project");
	}

	$defaultBranch = $data["branch"];
	$newBranch = "qa/update-maturity-" .  randValue();;

	$newSha = getShaAndCreateBranch($data["project"], $data["token"], $defaultBranch, $newBranch);
	
	$content = $data["maturity_data"];
	$content["codes"] = $data["new_codes"];
	$content["ignore"] = ($data["new_ignore"] == "true") ? true : false;
	if (isset($data["new_version_json"])) {
		$content["version_json"] = $data["new_version_json"];
	}

	$content_string = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	$fileSha = getFileBlobSha($data["project"], $data["token"], $path, $newBranch);

	$success = upsertFile($data["project"], $data["token"], $content_string, $path, $fileSha, $newBranch);
	if (!$success) 
	{
		returnBadRequest("error updating file");
	}

	$pr_url = createPullRequest($data["project"], $data["token"], $defaultBranch, $newBranch, $data["tag"]);

	if ($pr_url == null) {
		returnBadRequest("error create pull request");	
	}

	header('Content-Type: application/json');
	$resultJson["pr_url"] = $pr_url;
	echo json_encode($resultJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	
	exit;
}

function createPullRequest($project, $token, $srcBranch, $destBranch, $gifTag) 
{
	$url = "https://api.github.com/repos/" . $project . "/pulls";

	$body = "";

	$gifUrl = getRandGifUrl($gifTag);
	if ($gifUrl != null) 
	{
		$body = "![".$gifTag."](".$gifUrl.")\n\n";
	}

	$body .= "Updates maturity file with new maturity info :)";

	$data = array(
		"title" => "[QA] Update maturity info",
		"head" => $destBranch, 
		"base" => $srcBranch, 
		"body" => $body
	);

	$data_string = json_encode($data);                                                                                   

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Authorization: token ' . $token,
	    'Content-Type: application/json',
	    'Accept: application/vnd.github.shadow-cat-preview'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	$resultObj = json_decode($result);

	if (isset($resultObj->html_url)) {
		return $resultObj->html_url;
	}

	return $html_url;
}

function getShaAndCreateBranch($project, $token, $currentBranch, $newBranch) 
{
	$sha = getBranchRef($project, $token, $currentBranch);

	if ($sha == null) {
		returnBadRequest("error get ". $currentBranch ." sha");
	}
	
	$newSha = createBranch($project, $token, $sha, $newBranch);

	if ($newSha == null) {
		returnBadRequest("error create new branch");
	}

	return $newSha;
}

function getFileBlobSha($project, $token, $path, $branch)
{
	$url = "https://api.github.com/repos/" . $project . "/contents/" . $path . "?ref=" . $branch;

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Authorization: token ' . $token,
	    'Accept: *'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	$resultObj = json_decode($result);

	if (isset($resultObj->sha)) {
		return $resultObj->sha;
	}

	return null;
}

function upsertFile($project, $token, $content, $path, $sha, $branch) 
{
	$url = "https://api.github.com/repos/" . $project . "/contents/" . $path;

	$data = array(
		"message" => "Update maturity info",
		"commit" => array(
			"name" => "Thiago Barradas",
			"email" => "thiagobrowskt@gmail.com"
		),
		"content" => base64_encode($content),
		"branch" => $branch
	);

	if ($sha != null && $sha != "") {
		$data["sha"] = $sha;
	}

	$data_string = json_encode($data);                                                                                   

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); 
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Authorization: token ' . $token,
	    'Content-Type: application/json',
	    'Accept: *'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	return $code == 200 || $code == 201;
}

function createBranch($project, $token, $sha, $newBranch) 
{
	$url = "https://api.github.com/repos/" . $project . "/git/refs";

	$data = array(
		"ref" => "refs/heads/" . $newBranch,
		"sha" => $sha
	);

	$data_string = json_encode($data);                                                                                   

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Authorization: token ' . $token,
	    'Content-Type: application/json',
	    'Accept: *'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	$resultObj = json_decode($result);

	if (isset($resultObj->object) && isset($resultObj->object->sha)) {
		return $resultObj->object->sha;
	}

	return null;
}

function getBranchRef($project, $token, $branch)
{
	$url = "https://api.github.com/repos/" . $project . "/git/refs/heads/" . $branch;

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Authorization: token ' . $token,
	    'Accept: *'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	$resultObj = json_decode($result);

	if (isset($resultObj->object) && isset($resultObj->object->sha)) {
		return $resultObj->object->sha;
	}

	return null;
}

function returnBadRequest($message) 
{
	header( 'HTTP/1.1 400 Bad Request' );
	$result["message"] = $message;
	echo json_encode($result, true);
	exit;
}

function randValue()
{
	$val = strtolower(sprintf('%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535)));
    return $val;
}

function getRandGifUrl($tag) 
{
	$url = "https://api.giphy.com/v1/gifs/random";

	$url .= "?key=dc6zaTOxFJmzC";
	$url .= "&type=random";
	$url .= "&rating=pg-13";
	$url .= "&tag=" . $tag;

	$ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    'Accept: application/json'
	));

	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/' . $curl_version['version']);

	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	$resultObj = json_decode($result);

	if (isset($resultObj->data)) {
		return $resultObj->data->images->fixed_height->webp;
	}

	return null;
}

?>