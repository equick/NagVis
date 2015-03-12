<?php
 
if (!defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

$wgExtensionFunctions[] = "wfNagVisExtension";

# NagVis extension credits

$wgExtensionCredits['parserhook'][] = array(
        'name' => 'NagVis (version 1.07)',
	'version' => '1.07',
        'author' => 'Felipe Muñoz Brieva (email: felipe@gestiononline.net)',
        'url' => 'http://www.gestiononline.net/mediawiki/index.php?title=Gestion_Online:NagVisExtension',
        'description' => 'Add NagVis maps for Nagios/Icinga to mediawiki pages'
);

# NagVis extension 

function wfNagVisExtension() {
	global $wgParser;
	$wgParser->setHook( "NagVis", "renderNagVis" );
}
 
# NagVis parser

function renderNagVis( $input, array $args, Parser $parser ) {

	global $existsNagVisTag;
 
	if ($existsNagVisTag){
		return"<br><font color=red><em>---  NagVis tag ignored, only one tag per wiki page!   ---</em></font><br>";
	}

	$existsNagVisTag = true;

	// ###### INVALIDATE CACHE ######

	$parser->disableCache();
 
	// ###### LOAD NAGVIS ######

	$output="";
  
	$map="";
	$showHeader="yes";
	$urlNagVis="";
	$urlMonitor="";
	$nagvisPath="";
	$mapTitle="";
	$monitorSystem="icinga";
 
	foreach( $args as $name => $value ){
		switch(strtolower(htmlspecialchars($name))){ 
		case 'map':
			$map=htmlspecialchars($value);
			break;
		case 'showheader':
			$showHeader=htmlspecialchars($value);
			break;
		case 'urlnagvis':
			$urlNagVis=htmlspecialchars($value);
			break;
		case 'urlmonitor':
			$urlMonitor=htmlspecialchars($value);
			break;
		case 'nagvispath':
			$nagvisPath=htmlspecialchars($value);
			break;
		case 'system':
			$monitorSystem=htmlspecialchars($value);
			break;
		}
	}

	if (trim($input)==""){
		$mapTitle=$map;
	} else {
		$mapTitle=htmlspecialchars($input);
	}

	// -------------------------------------------

	require_once('includes/simple_html_dom.php');

	$param["showHeader"]=$showHeader; 
	$param["monitorSystem"] = $monitorSystem; 
	$param["mapTitle"] = $mapTitle; 
  
	// Check: Exists NagVis URL? 

	if(!urlExists($urlNagVis)){
		$output.="<br><font color=red>";
		$output.="ERROR: (urlNagVis)<br>";
		$output.="Check your URL: $urlNagVis<br>";
		$output.="</font><br>";
		return $output;
	}

	// Load NagVis Map DOM 

	$html=new simple_html_dom();

        $param["urlNagiosBase"] = 'http://'.str_replace('index.php','',str_replace('http://','',$urlMonitor)); 
        $param["urlNagVisBase"] = 'http://'.str_replace('/frontend/nagvis-js/index.php','',str_replace('http://','',$urlNagVis));
        $param["urlNagVisMap"] = $param["urlNagVisBase"]."/frontend/nagvis-js/index.php?mod=Map&act=view&show=".$map; 
	$param["nagvisPath"]="/nagvis";
	$param["map"]=$map;

        $html->load_file($param["urlNagVisMap"]);
        $output.= NagVis_v1_8($param,$html);

	$html->clear();
	unset($html);

	return $output;

}

/* NagVis release 1.8 */

function NagVis_v1_8($param,$html) {

	global $wgOut,     $wgScriptPath;
	$output="";
	$domain = 'http://'. parse_url($param["urlNagVisBase"])['host'];

	// Wrapper wiki offset
	$wiki_offsety='wiki_offsety = (document.getElementById(obj.conf.object_id+"-icondiv").offsetTop) + 20;';
	$wiki_offsetx='wiki_offsetx = (document.getElementById(obj.conf.object_id+"-icondiv").offsetLeft) + 20;';

	// Start "div" in MediaWiki page for NagVis Maps
	$wgOut->addHTML('<div id="codeNagVis">');

	// Scripts src -----------------------------------------------------------------
	$scriptssrc=$html->find('script[src]');

	// Iterate through js scripts and replace url paths with remote NagVis site
	foreach ($scriptssrc as $s) {
		$tmpscript=str_replace($param["nagvisPath"]."/",$param["urlNagVisBase"]."/",$s->src);
		$file1=file($tmpscript);

		$wgOut->addHTML('<script language="JavaScript" type="text/javascript">');

		foreach($file1 as $ss) {
			$ss=str_replace('href="#"', 'href="' . $param["urlNagVisBase"] . '"',$ss);
			$ss=str_replace('oGeneralProperties.path_server', '"' . $param["urlNagVisBase"] . '/server/core/ajax_handler.php"',$ss);
			$ss=str_replace('oGeneralProperties.path_images', '"' . $param["urlNagVisBase"] . '/frontend/nagvis-js/images/"',$ss);
			$ss=str_replace('oGeneralProperties.path_iconsets', '"' . $param["urlNagVisBase"] . '/userfiles/images/iconsets/"',$ss);
			$ss=str_replace('oProperties.background_image', '"' . $param["urlNagVisBase"] . '/userfiles/images/maps/' . $param["map"] . '.png"',$ss);
			$ss=str_replace('oGeneralProperties.path_cgi', '"' . $param["urlNagiosBase"] . '/cgi-bin/"',$ss);
			$ss=str_replace('this.conf.htmlcgi', '"' . $param["urlNagiosBase"] . '/cgi-bin"',$ss);
			$ss=str_replace('oLink.href = ', 'oLink.href = "' . $domain . '" + ',$ss);

			
			//fix position of hover pop up window using the wiki offsets
			$ss=str_replace('obj.hoverY = y;',"obj.hoverY = y;".$wiki_offsety,$ss);
			$ss=str_replace('obj.hoverX = x;',"obj.hoverX = x;".$wiki_offsetx,$ss);
			$ss=str_replace("hoverMenu.style.top = (y + scrollTop + hoverSpacer - getHeaderHeight()) + 'px';","hoverMenu.style.top = wiki_offsety + 'px';",$ss);
		        $ss=str_replace("hoverMenu.style.left = (x + scrollLeft + hoverSpacer - getSidebarWidth()) + 'px';","hoverMenu.style.left = wiki_offsetx + 'px';",$ss);

			//fix position of right click menu
			$ss=str_replace("contextMenu.style.top = event.clientY + scrollTop - getHeaderHeight() + 'px';","contextMenu.style.top = wiki_offsety + 'px';",$ss);
		        $ss=str_replace("contextMenu.style.left = event.clientX + scrollLeft - getSidebarWidth() + 'px';","contextMenu.style.left = wiki_offsetx + 'px';",$ss);

			$ss=str_replace('document.body','document.getElementById("nagvis")',$ss);
			$wgOut->addHTML($ss);
		}
		$wgOut->addHTML('</script>');
	}

	// Links language ------------------------------------------------------------

	$links=$html->find('link[type]');

	// Iterate through link resources, replace url paths with remote NagVis site, and pull css stylesheets
	foreach ($links as $s) {
		$s->href=str_replace($param["nagvisPath"].'/',$param["urlNagVisBase"]."/",$s->href);
		$contentcsssheet=new simple_html_dom();
		$contentcsssheet->load_file($s->href);
		$contentcsssheet=$contentcsssheet->__toString();
		$contentcsssheet=str_replace('body, th, td','not_used',$contentcsssheet);
		$contentcsssheet=str_replace('../../frontend/nagvis-js/images/internal', 'imgPath+\'',$contentcsssheet);
		$valor=str_replace('#backgroundImage','backgroundImage',$contentcsssheet);
		$wgOut->addHTML('<style type="text/css">');
		$wgOut->addHTML($valor);
		$wgOut->addHTML('</style>');
		unset($contentcsssheet);
	}

	// Disable bottom-border around anchors
	$wgOut->addHTML('<style type="text/css">');
	$wgOut->addHTML('#nagvis a{ border-bottom: none; }');
	$wgOut->addHTML('</style>');

	// Closing div for id=codeNagVis
	$wgOut->addHTML('</div>');
 
	$output.=('<div id="nagvis">');

	// Header for NagVis map
	if ($param["showHeader"]=="yes"){
		$output.=('<div id="header" style="display:inline;">');
		$output.=('<div id="headerspacer" style="display:inherit;">');
		$output.=('<div id="headershow" style="border:0; display:inherit;">');
		$output.=('<table style="background-color: #F5F5F5; border-collapse: collapse; width: 75%;">');
		$output.=('<tbody>');
		$output.=('<td style="width: 25px; text-align: center; border: 1px #a4a4a4 solid; padding: 2px;">');
		$output.=('<a href='.$param["urlNagVisMap"].'>');
		$output.=('<img alt="Nagios" src='.$wgScriptPath.'/extensions/NagVis/images/nagvis.png>');
		$output.=('</a>');
		$output.=('</td>');
		$output.=('<td style="width: 25px; text-align: center; border: 1px #a4a4a4 solid; padding: 2px;">');
		$output.=('<a href='.$param["urlNagiosBase"].'>');

		if ($param["monitorSystem"]=="nagios"){
			$output.='<img alt="Nagios" src='.$wgScriptPath.'/extensions/NagVis/images/nagios.png>';
		} else {
			$output.='<img alt="Icinga" src='.$wgScriptPath.'/extensions/NagVis/images/icinga.png>';
		}

		$output.=('</a>');
		$output.=('</td>');
		$output.=('<td style="text-align: center; border: 1px #a4a4a4 solid; padding: 2px;font-size: 18px; color: #314455;">');
		$output.=('<a style="color: black;">'.$param["mapTitle"].'</a>');
		$output.=('</td>');
		$output.=('</tbody>');
		$output.=('</table>');
		$output.=('</div></div></div>');
	}

	// Add NagVis Map
	$output.=('<div id="map" class="map"></div>');

	// Scripts language ------------------------------------------------------------
	$scriptslanguage=$html->find('script');

	foreach ($scriptslanguage as $s){
		if(!isset($s->src)) {
			$wgOut->addHTML($s);
		}
	}

	// Closing div for id=nagvis
	$output.=('</div>');

	return $output;

}

// Check : Exists URL ?
function urlExists($url) {
	$hdrs = @get_headers($url);
	return is_array($hdrs) ? preg_match('/^HTTP\\/\\d+\\.\\d+\\s+2\\d\\d\\s+.*$/',$hdrs[0]) : false;
}

?>
