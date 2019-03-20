<?php

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
	
	default:
		var_dump($data);
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

function showMarkdown($data) 
{
	echo "markdown\n";
}

function showMaturity($data) 
{
	echo "maturity\n";
}

function getData()
{
	$data["command"] = getQueryProperty("command");
	$team = getQueryProperty("team");
	$project = getQueryProperty("project");
	$token = getQueryProperty("token");
	$json =  getQueryProperty("json");
	$codes =  getQueryProperty("codes");
	$title = getQueryProperty("title");

	$data["type"] = "undefined";
	if ($project != null) 
	{
		$data["type"] = "project";
		$data["token"] = $token;
		$data["project"] = $project;
		$data["title"] = $project;
		$data["maturity_data"] = getProjectMaturityData($project, $token);
	} 

	if ($team != null) 
	{
		$data["type"] = "team";
		$data["token"] = $token;
		$data["team"] = $team;
		$data["title"] = "team:" . $team;
		$data["maturity_data"] = getTeamMaturityData($project, $token);	
	}

	if ($json != null) 
	{
		$data["type"] = "json";
		$data["codes"] = $codes;
		$data["title"] = ($title != null) ? $title : "undefined";
		$data["maturity_data"]["version_json"] = $json;
		$data["maturity_data"]["codes"] = explode("|", $codes);	
	}

	$data["maturity_model"] = getMaturityModel($data["maturity_data"]["version_json"]);
	$data["maturity"] = processMaturity($data);
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

	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$result = curl_exec($ch);

	curl_close($ch);

	return json_decode($result, true);
}

function getTeamMaturityData($team, $token) 
{
	// TODO
	$data["version_json"] = "team maturity";
	return $data;
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

	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$result = curl_exec($ch);

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

?>