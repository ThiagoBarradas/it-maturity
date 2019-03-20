var globalBaseUrl = window.location.origin;
var globalCodes = [];
var globalData = {};
var globalImage = "";
var globalVersionUrl = "";

$(document).ready(function() {
		
	var urlParams = new URLSearchParams(window.location.search);

	var readonly = urlParams.has("readonly");
	var action = urlParams.has("action") ? urlParams.get("action") : "details";  // badge, markdown, details
	globalCodes = urlParams.has("codes") ? urlParams.get("codes").split("|") : []; 
	globalVersionUrl = urlParams.get("url");
	
	$.getJSON(globalVersionUrl).done(function(data) {
		globalData = data;
		
		console.log(data);
		document.title = globalData.name;
		
		switch (action) {
			case "markdown":
				ShowMarkdown();
				break;
			case "badge":
				CheckCodes();
			    CalcLevel();
			    ReturnImage();
				break;
			default:
				ShowDataInHtml();
				CheckCodes();
				CalcLevel();
				break;
		}
				
		$("#loader").fadeOut();
		$("#content").fadeIn();
	})
	.fail(function() {
		$("#loader").fadeOut();
		$("#error").fadeIn();
	});
	
	$("#copy-markdown").click(function () {
		var copyText = document.getElementById("badge-markdown");
		copyText.select();
		document.execCommand("copy");

		alert("Badge markdown code copied successfull!");
	});
});

function CalcLevel() {
	
	var color = globalData.empty_color;
	var level = globalData.empty_level;
	var mustBreak = false;
	
	for (var i=0; i < globalData.levels.length; i++) {
		for (var j=0; j < globalData.levels[i].items.length; j++) {
			
			if (globalCodes.indexOf(globalData.levels[i].items[j].code) < 0)
			{
				mustBreak = true;
			}
			
			if (mustBreak) break;
		}
		
		if (mustBreak) break;
		
		level = globalData.levels[i].level;
		color = globalData.levels[i].color;
	}
	
	var name = encodeURIComponent(globalData.badge_name)
	globalImage = "https://img.shields.io/badge/"+ name +"-" + level + "-" + color + ".svg";
	
	$("#badge").attr("src","https://img.shields.io/badge/"+ name +"-" + level + "-" + color + ".svg");
		
	UpdateMarkdown(level);	
}

function UpdateMarkdown(level) {
	var codes = globalCodes.join('|');
	var url = globalBaseUrl + "/index.html?url=" + globalVersionUrl + "&codes=" + codes;
	
	markdown = "[![" + globalData.badge_name + " " + level + "](" + globalImage + ")](" + url + ")";
	
	$("#badge-markdown").val(markdown);
	
	console.log("===== Markdown")
	console.log(markdown);
}

function CheckCodes() { 
	for (var i=0; i < globalCodes.length; i++)
	{
		if ( $("#" + globalCodes[i]).length ) {
			$("#" + globalCodes[i]).prop('checked', true);
		}
	}
	
	console.log("===== Current Codes")
	console.log(globalCodes);
}

function ShowDataInHtml() {
	$("#markdown-content").hide();
	
	var urlParams = new URLSearchParams(window.location.search);
	var readonly = urlParams.has("readonly");
	
	// rules
	for (var i=0; i < globalData.levels.length; i++) {
		
		var panelId = globalData.name + globalData.levels[i].level + "-panel";
		
		$("#rules-content").append("<div id=\"" + panelId + "\" class=\"panel panel-info\"></div>");
		$("#" + panelId).append("<div class=\"panel-heading\">" + globalData.name + " " + globalData.levels[i].level + " </div>");
		
		var panelBodyId = globalData.name + globalData.levels[i].level + "-body";
		$("#rules-content").append("<div id=\"" + panelBodyId + "\" class=\"panel-body\"></div>");
			
		globalData.levels[i].items.sort((a,b) => (a.category > b.category) ? 1 : ((b.category > a.category) ? -1 : 0));

		lastCategory = "";		
		for (var j=0; j < globalData.levels[i].items.length; j++) {
			
			var category = globalData.levels[i].items[j].category;
			var categoryNormalized = category.replace(/ /g,'').replace(/&/g,"And");
			var code = globalData.levels[i].items[j].code;
			var details = globalData.levels[i].items[j].details;
			var description = "[" + code + "] " + globalData.levels[i].items[j].description;
			
			if (lastCategory != categoryNormalized)
			{
				var ulId = globalData.name + globalData.levels[i].level + categoryNormalized + "-ul";
				$("#" + panelBodyId).append("<ul id=\"" + ulId + "\"><li>" + globalData.levels[i].items[j].category + "</li></ul>");
			}
			
			var readonlyText = (readonly === true) ? "disabled=\"disabled\"" : "" ;
			$("#" + ulId).append("<div class=\"chk-item\"><input " + readonlyText + " type=\"checkbox\" id=\""+ code +"\" name=\""+ code +"\" value=\""+ code +"\"><a href=\"" + details +"\" target=\"_blank\">" + description + "</a></div>");
			
			lastCategory = categoryNormalized;
		}
    }
	
	// footer
	$(".name").text(globalData.name);
	$(".release-at").text(globalData.release_at);
	$(".version").text(globalData.version);
	
	$('input[type=checkbox]').change(function(event) {
		var id = event.currentTarget.value;
		
		if ( $("#" + id).is(":checked") === true) 
		{
			if(globalCodes.indexOf(id) === -1) {
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
		
		CalcLevel();
	});
}

function ShowMarkdown() {
	
	$("#content > header").hide();
	$("#content > footer").hide();
	$("#rules-content").hide();
	
	$("#markdown-content").append("<pre></pre>");
	$("#markdown-content pre").append("## " + globalData.name + "(version " + globalData.version + " - " +  globalData.release_at + " )\n");
		
	// rules
	for (var i=0; i < globalData.levels.length; i++) {
		
		$("#markdown-content pre").append("\n");
		$("#markdown-content pre").append("> " + globalData.name + " " + globalData.levels[i].level + "\n");
			
		globalData.levels[i].items.sort((a,b) => (a.category > b.category) ? 1 : ((b.category > a.category) ? -1 : 0));

		lastCategory = "";		
		
		for (var j=0; j < globalData.levels[i].items.length; j++) {
			
			var category = globalData.levels[i].items[j].category;
			var code = globalData.levels[i].items[j].code;
			var details = globalData.levels[i].items[j].details;
			var description = "[" + code + "] " + globalData.levels[i].items[j].description;

			if (lastCategory != category)
			{
				$("#markdown-content pre").append(" > - #### " + category + "\n");
			}
			
			$("#markdown-content pre").append(" >   - [ ] [" + description + "](" + details + ")\n");
			
			lastCategory = category;
		}
	}	
}

function ReturnImage()
{
	document.open();
	document.write(globalImage);
    document.close();
}