var globalCodes = [];
var lastResult = {};
var originalBadge = "";
var mode = "nda";


$(document).ready(function() {
	
	var urlParams = new URLSearchParams(window.location.search);

	var readonly = urlParams.has("readonly");
	var codes = urlParams.has("codes") ? urlParams.get("codes").split("|") : []; 
	var versionUrl = urlParams.get("version_json");
	var project = urlParams.get("project");
	var token = urlParams.get("token");

	if (project != null)
	{		
		$("#project-project").val(project);
		$("#project-token").val(token);
		processMaturityByProject();
	}
	else if (versionUrl != null)
	{
		$("#manually-version").val(versionUrl);
		$("#manually-codes").val(codes);
		$("#manually-button").click();
	}
	else
	{
		$("#header").fadeIn();
	    $("#content").fadeIn();
		$("#generator").fadeIn();
		$("#loader").fadeOut();
	}

	// process maturity manually
	$("#manually-button").click(function () {
		$("#loader").fadeIn();
		globalCodes = [];
		processMaturityByCodes();
	});

	// process maturity by projoect(s)
	$("#project-button").click(function () {
		$("#loader").fadeIn();
		globalCodes = [];
		processMaturityByProject();
	});

	// process maturity by projoect(s)
	$("#pr-button").click(function () {
		
		var tag = $("#pr-tag").val();
		var branch = $("#pr-branch").val();
		var new_ignore = $("#pr-ignore").is(":checked") === true ? "true" : "false"; 
		var include = $("#pr-include").is(":checked") === true ? "true" : "false"; 
		var new_version = $("#pr-version").val();
		var new_codes = globalCodes.filter(Boolean).join("|");
		var project = lastResult.project;
		var token = lastResult.token;
		
		project = $("#pr-project").val();
		token = $("#pr-token").val();

		$("#loader").fadeIn();
		$("#content").fadeOut();
		$("#header").fadeOut();
		$("#footer").fadeOut();

		var url = getUrl() + "?command=pull_request&project=" + project + "&token=" + token + "&new_version_json=" + new_version + "&new_codes=" + new_codes + "&branch=" + branch + "&new_ignore=" + new_ignore + "&tag=" + tag + "&include=" + include;
		
		$.getJSON(url).done(function(data) {
			$("#pr-link").html("<a href=\""+data.pr_url+"\" target=\"_blank\">" + data.pr_url + "</a>");
		})
		.fail(function() {
			showError("#error", "Error getting maturity data per project, please, check project and token!")
		})
		.always(function() {
		    $("#loader").fadeOut();
			$("#content").fadeIn();
			$("#header").fadeIn();
			$("#footer").fadeIn();
		});
	});

	// enable tooptip
	$(function () {
	  $('[data-toggle="tooltip"]').tooltip()
	});

	// click co copy badge markdown
	$("#copy-markdown-badge").click(function () { 
		copyContentFrom("markdown-badge", "Badge markdown code copied successfull!");
	});
});

function processMaturityByCodes() {
	
	$("#header").fadeOut();
	$("#footer").hide();
	$("#error").fadeOut();
	$("#content").fadeOut();
	$("#result").fadeOut();

	var version = $("#manually-version").val();
	var codes = $("#manually-codes").val();
	
	getMaturityData(version, codes).done(function(data) {
		
		console.log(data);
		lastResult = data;

		showTitle(data);
		showMaturity(data);
		showBadge(data.maturity.badge, true);
		showRaw(data);
		showMarkdown(data);
		$("a[aria-controls=send-pr]").show();
		
		$(".name").html(data.maturity_model.name);
		$(".release-at").html(data.maturity_model.release_at);
		$(".version").html(data.maturity_model.version);
		$("#footer").fadeIn();
		$("#result").fadeIn();
	})
	.fail(function() {
		showError("#error", "Error getting maturity data per project, please, check project and token!")
	})
	.always(function() {
	    $("#content").fadeIn();
	    $("#header").fadeIn();
	    $("#loader").fadeOut();
	});
}

function processMaturityByProject() {
	
	$("#header").fadeOut();
	$("#footer").hide();
	$("#error").fadeOut();
	$("#content").fadeOut();
	$("#result").fadeOut();

	var project = $("#project-project").val();
	var token = $("#project-token").val();
	
	getMaturityDataByProject(project, token).done(function(data) {
		
		console.log(data);
		lastResult = data;

		showTitle(data);
		showMaturity(data);
		showBadge(data.maturity.badge, true);
		showRaw(data);
		showMarkdown(data);
		
		$(".name").html(data.maturity_model.name);
		$(".release-at").html(data.maturity_model.release_at);
		$(".version").html(data.maturity_model.version);
		$("#footer").fadeIn();
		$("#result").fadeIn();
	})
	.fail(function() {
		showError("#error", "Error getting maturity data per project, please, check project and token!")
	})
	.always(function() {
	    $("#content").fadeIn();
	    $("#header").fadeIn();
	    $("#loader").fadeOut();
	});
}

function showTitle(data) {
	$("#tab-info").show();
	$("#maturity-markdown").show();
	$("#tab-info").click();

	if (data.projects != null) {
		mode = "projects";
		$("#send-pr-manually-mode").hide();

		$("#header").html("<h1>maturity for:</h1> <ul></ul>");
		$("#projects-details").html("Includes: <ul class=\"pj-includes\"></ul><br>Ignores: <ul class=\"pj-ignores\"></ul>");
			
		for (var i = data.projects_maturity.length - 1; i >= 0; i--) {
			
			var cur = data.projects_maturity[i];

			var github_link = "<b><a target=\"_blank\" href=\"https://github.com/" + cur.project + "\">" + cur.project + "</a></b>";
			var badge = "<a target=\"_blank\" href=\"index.html?project=" + cur.project + "&token=" + lastResult.token + "\"><img src=\"" + cur.maturity.badge + "\" /></a>";

			$("#header ul").append("<li>" + github_link + "</li>");				
			$("#projects-details ul.pj-includes").append("<li>" + badge + " - " + github_link + "</li>");				
		}

		for (var i = data.ignored_maturity.length - 1; i >= 0; i--) {
			
			var cur = data.ignored_maturity[i];

			var github_link = "<b><a target=\"_blank\" href=\"https://github.com/" + cur.project + "\">" + cur.project + "</a></b>";
			var badge = "<a target=\"_blank\" href=\"index.html?project=" + cur.project + "&token=" + lastResult.token + "\"><img src=\"" + cur.maturity.badge + "\" /></a>";

			$("#header ul").append("<li>" + github_link + "</li>");				
			$("#projects-details ul.pj-ignores").append("<li>" + badge + " - " + github_link + "</li>");				
		}

		if (data.projects_maturity.length == 0) {
			$("#projects-details ul.pj-includes").append("<li>no projects</li>");		
		}

		if (data.ignored_maturity.length == 0) {
			$("#projects-details ul.pj-ignores").append("<li>no projects</li>");
		}

		$("#maturity-markdown").hide();
		$("#tab-info").hide();
		$("#tab-projects").show();
		$("#tab-projects").click();

	} else if (data.project != null) {
		mode = "project";
		$("#send-pr-manually-mode").hide();
		$("#tab-projects").hide();
		$("#header h1").html("maturity for <b><a target=\"_blank\" href=\"https://github.com/" + data.project + "\">" + data.project + "</a></b>");

		$("#pr-project").val(data.project);
		$("#pr-token").val(data.token);

		if (data.token == null || data.token == "") {
			$("#send-pr-manually-mode").show();			
		}
	} else {
		mode = "manually";
		$("#send-pr-manually-mode").show();
		$("#tab-projects").hide();
		$("#header h1").html("processed maturity");
	}
}

function showMaturity(full_data) {
	var urlParams = new URLSearchParams(window.location.search);

	// rules
	if (full_data.maturity_model == null)
	{
		full_data.maturity_model = {};
		full_data.maturity_model.levels = [];
		full_data.maturity_data = {};
		full_data.maturity_data.codes = [];
		full_data.maturity_data.version_json = "undefined";
	}

	$("#pr-version").val(full_data.maturity_data.version_json);
	$("#maturity-details").html("");

	for (var i=0; i < full_data.maturity_model.levels.length; i++) {
		
		var data = full_data.maturity_model;

		var panelId = data.name + data.levels[i].level + "-panel";
	
		$("#maturity-details").append("<div id=\"" + panelId + "\" class=\"panel panel-info\"></div>");

		$("#" + panelId).append("<div class=\"panel-heading\">" + data.name + " " + data.levels[i].level + " </div>");
		
		var panelBodyId = data.name + data.levels[i].level + "-body";
		$("#maturity-details").append("<div id=\"" + panelBodyId + "\" class=\"panel-body\"></div>");
			
		data.levels[i].items.sort((a,b) => (a.category > b.category) ? 1 : ((b.category > a.category) ? -1 : 0));

		lastCategory = "";		
		for (var j=0; j < data.levels[i].items.length; j++) {
			
			var category = data.levels[i].items[j].category;
			var categoryNormalized = category.replace(/ /g,'').replace(/&/g,"And");
			var code = data.levels[i].items[j].code;
			var details = data.levels[i].items[j].details;
			var description = "[" + code + "] " + data.levels[i].items[j].description;
			
			if (lastCategory != categoryNormalized)
			{
				var ulId = data.name + data.levels[i].level + categoryNormalized + "-ul";
				$("#" + panelBodyId).append("<ul id=\"" + ulId + "\"><li>" + data.levels[i].items[j].category + "</li></ul>");
			}
			
			$("#" + ulId).append("<div class=\"chk-item\"><input onchange=\"changeCheckbox(this)\" type=\"checkbox\" id=\""+ code +"\" name=\""+ code +"\" value=\""+ code +"\"><a href=\"" + details +"\" target=\"_blank\">" + description + "</a></div>");
			
			lastCategory = categoryNormalized;
		}
    }

	globalCodes = full_data.maturity_data.codes.slice(0).filter(Boolean);

	updateCheckedCodes();
}

function showMarkdown(data) {
	$("#markdown-badge").val(data.badge_markdown);

	$("#markdown-version").html("");
	$("#markdown-version").append("## " + data.maturity_model.name + " (Version " +  data.maturity_model.version + " - " +   data.maturity_model.release_at + " )\n");
		
	// rules
	for (var i=0; i <  data.maturity_model.levels.length; i++) {
		
		$("#markdown-version").append("\n");
		$("#markdown-version").append("> " +  data.maturity_model.name + " " +  data.maturity_model.levels[i].level + "\n");
			
		 data.maturity_model.levels[i].items.sort((a,b) => (a.category > b.category) ? 1 : ((b.category > a.category) ? -1 : 0));

		lastCategory = "";		
		
		for (var j=0; j <  data.maturity_model.levels[i].items.length; j++) {
			
			var category =  data.maturity_model.levels[i].items[j].category;
			var code =  data.maturity_model.levels[i].items[j].code;
			var details =  data.maturity_model.levels[i].items[j].details;
			var description = "[" + code + "] " +  data.maturity_model.levels[i].items[j].description;

			if (lastCategory != category)
			{
				$("#markdown-version").append(" > - #### " + category + "\n");
			}
			
			var marked = globalCodes.includes(code) ? "[x]" : "[ ]" ;

			$("#markdown-version").append(" >   - " + marked + " [" + description + "](" + details + ")\n");
			
			lastCategory = category;
		}
	}	
}

function showBadge(url) {
	$("#result-img").attr("src", url);

	if (isModified()) {
		$("a[aria-controls=badge]").hide();
		$("a[aria-controls=raw]").hide();
		$("a[aria-controls=send-pr]").show();

		$("#result-img-modified").show();
	} else {
		$("a[aria-controls=badge]").show();
		$("a[aria-controls=raw]").show();
		$("a[aria-controls=send-pr]").hide();

		$("#result-img-modified").hide();
	}
}

function isModified() {
	var originalCodes = lastResult.maturity_data.codes;

	originalCodes.sort((a,b) => (a > b) ? 1 : ((b > a) ? -1 : 0));
	globalCodes.sort((a,b) => (a > b) ? 1 : ((b > a) ? -1 : 0));	

	return !arrays_equal(originalCodes, globalCodes);
}

function arrays_equal(a,b) { 
	var result = !!a && !!b && !(a<b || b<a); 
	return result;
}

function showRaw(data) {
	$("#result-pre").text(JSON.stringify(data, null, 4));
}

function showError(errorSelector) {
	$(errorSelector).fadeIn();

	setTimeout(function() {
		$(errorSelector).fadeOut();
    }, 3000);
}

function copyContentFrom(id, message) {
	var copyText = document.getElementById(id);
	copyText.select();
	document.execCommand("copy");

	alert(message);
}

function updateCheckedCodes() { 
	for (var i=0; i < globalCodes.length; i++)
	{
		if(globalCodes[i] != null && globalCodes[i] != "")
		{
			if ( $("#" + globalCodes[i]).length ) {
				$("#" + globalCodes[i]).prop('checked', true);
			}
		}
	}
}

function changeCheckbox(chk) {
	if (chk != null || chk.value != null)
	{
		var id = chk.value;

		if ( $("#" + id).is(":checked") === true) 
		{
			var index = globalCodes.indexOf(id); 
			if(index === -1) {
			  globalCodes.push(id);
			}
		}
		else 
		{
			var index = globalCodes.indexOf(id); 
			if (index !== -1) {
				globalCodes.splice(index, 1);
			}
		}
	}
	
	calcLevel();
}

function calcLevel() {
	console.log(lastResult);
	var color = lastResult.maturity_model.empty_color;
	var level = lastResult.maturity_model.empty_level;
	var mustBreak = false;
	
	for (var i=0; i < lastResult.maturity_model.levels.length; i++) {
		for (var j=0; j < lastResult.maturity_model.levels[i].items.length; j++) {
			
			if (globalCodes.indexOf(lastResult.maturity_model.levels[i].items[j].code) < 0)
			{
				mustBreak = true;
			}
			
			if (mustBreak) break;
		}
		
		if (mustBreak) break;
		
		level = lastResult.maturity_model.levels[i].level;
		color = lastResult.maturity_model.levels[i].color;
	}
	
	var name = encodeURIComponent(lastResult.maturity_model.badge_name)
	var img = "https://img.shields.io/badge/"+ name +"-" + level + "-" + color + ".svg";
	
	showBadge(img, false);
}

function getMaturityDataByProject(project, token) {
	if (project.includes("|")) {
		return getMaturityDataByProjects(project, token);
	}

	var url = getUrl() + "?project=" + project + "&token=" + token;
	return $.getJSON(url);
}

function getMaturityDataByProjects(projects, token) {
	var url = getUrl() + "?projects=" + projects + "&token=" + token;
	return $.getJSON(url);
}

function getMaturityData(version_json, codes) {
	var url = getUrl() + "?json=" + version_json + "&codes=" + codes;
	return $.getJSON(url);
}

function getUrl() {
	return window.location.origin + window.location.pathname.replace("index.html", "") +  "maturity.php";
}


