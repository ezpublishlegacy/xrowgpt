<?php

class xrowgpt
{

    public static function checkDisplayStatus()
    {
        $xrowgptINI = eZINI::instance("xrowgpt.ini");
        if ( $xrowgptINI->hasVariable( 'GeneralSettings', 'Display' ) )
        {
            $display_in_siteaccess = $xrowgptINI->variable( 'GeneralSettings', 'Display' );
        }
        else
        {
            $display_in_siteaccess = $xrowgptINI->variable( 'GeneralSettings', 'DisplayDefault' );
        }

        //check if the siteaccess is allowed to use ads
        if ( $display_in_siteaccess != "disabled")
        {
            $Module = $GLOBALS['eZRequestedModule'];
            $namedParameters = $Module->NamedParameters;

            if ( isset($namedParameters["NodeID"]) && is_numeric($namedParameters["NodeID"]) )
            {
                //check if its a single page exclude
                $node_id = $namedParameters["NodeID"];
                $single_page_excludes = $xrowgptINI->variable( 'GeneralSettings', 'SinglePageExcludes' );
                if ( in_array( $node_id, $single_page_excludes ) )
                {
                    return false;
                }

                //check if the node is excluded by a tree exclude
                $tree_excludes = $xrowgptINI->variable( 'GeneralSettings', 'TreeExcludes' );
                $tpl = eZTemplate::instance();
                $path = array();
                
                if ( $tpl->hasVariable('module_result') )
                {
                    $moduleResult = $tpl->variable('module_result');
                    foreach ( $moduleResult["path"] as $element )
                    {
                        $path[] = $element["node_id"];
                    }
                    
                }
                else if ( isset( $tpl->Variables[""]["node"] ) )
                {
                    //fallback just in case
                    $path = $tpl->Variables[""]["node"]->pathArray();
                }

                foreach ( $path as $path_element )
                {
                    if ( isset($path_element) && in_array( $path_element, $tree_excludes ) )
                    {
                        return false;
                    }
                }
            }
            //return true if no condition kicked us out before
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function getKeyword( $node = false )
    {
        //checks the path array reversive for a matching keyword inside the ini
        $tpl = eZTemplate::instance();
        $xrowgptINI = eZINI::instance("xrowgpt.ini");
        $path = array();
        $uri = "";
        //activate this to run testmode everywhere
        //return "test";

        if ( $tpl->hasVariable('module_result') )
        {
            $moduleResult = $tpl->variable('module_result');
            $uri = $moduleResult["uri"];

            foreach ( $moduleResult["path"] as $element )
            {
                if ( isset( $element["node_id"] ) )
                {
                    $path[] = $element["node_id"];
                }
            }
        }
        else if ( isset( $tpl->Variables[""]["node"] ) )
        {
            //fallback just in case
            $path = $tpl->Variables[""]["node"]->pathArray();
            $uri = $GLOBALS["request_uri"];
        }
        else if ($node != false && $node instanceof eZContentObjectTreeNode )
        {
            //fallback of the fallback
            $path = explode("/", $node->PathString);
            $uri = $node->urlAlias();
        }

        $keywords = $xrowgptINI->variable( 'KeywordSettings', 'KeywordMatching' );
        $ivw_keywords = $xrowgptINI->variable( 'KeywordSettings', 'IVWMatching' );
        //write "test" zone for test module
        if ( $uri == "/xrowgpt/test" )
        {
            return array( "keyword" => "test", "path" => $path, "ivw_keyword" => "test" );
        }
        else if( strpos($uri, "content/search") )
        {
            return array( "keyword" => $xrowgptINI->variable( 'KeywordSettings', 'KeywordDefault' ), "path" => $path, "ivw_keyword" => "suche", "ivw_sv" => "in" );
        }

        foreach ( array_reverse( $path ) as $path_element )
        {
            if ( isset($path_element) && array_key_exists($path_element, $keywords) )
            {
                //stop the foreach and return the matching keyword
                $normal_keyword = $keywords[$path_element];
                break;
            }
        }

        foreach ( array_reverse( $path ) as $path_element )
        {
            if ( isset($path_element) && array_key_exists($path_element, $ivw_keywords) )
            {
                //stop the foreach and return the matching keyword
                $ivw_keyword = $ivw_keywords[$path_element];
                break;
            }
        }
        
        //$ivw_sv = "in"; // in = frabo tag aktiv
        $ivw_sv = "i2"; //frabo tag activ async
        if( end($path) == $xrowgptINI->variable( 'IVWSettings', 'StartPage' ) )
        {
            $ivw_sv = "ke";
        }
        elseif ( $ivw_keyword === $ivw_keywords[$xrowgptINI->variable( 'IVWSettings', 'StartPage' )] )
        {
            unset($ivw_keyword);
        }

        if (isset($normal_keyword) && isset($ivw_keyword) )
        {
            return array( "keyword" => $normal_keyword, "path" => $path, "ivw_keyword" => $ivw_keyword, "ivw_sv" => $ivw_sv );
        }

        //no keyword found, use the default!
        if ( !isset($normal_keyword) && $xrowgptINI->hasVariable( 'KeywordSettings', 'SiteaccessKeywordDefault' ) )
        {
            $normal_keyword = $xrowgptINI->variable( 'KeywordSettings', 'SiteaccessKeywordDefault' );
        }
        elseif( !isset($normal_keyword) )
        {
            $normal_keyword = $xrowgptINI->variable( 'KeywordSettings', 'KeywordDefault' );
        }

        //no ivw keyword found, use the default!
        if ( $xrowgptINI->hasVariable( 'KeywordSettings', 'SiteaccessIVWKeywordDefault' ) )
        {
            $ivw_keyword = $xrowgptINI->variable( 'KeywordSettings', 'SiteaccessIVWKeywordDefault' );
        }
        elseif( !isset($ivw_keyword) )
        {
            $ivw_keyword = $xrowgptINI->variable( 'IVWSettings', 'KeywordDefault' );
        }
        return array( "keyword" => $normal_keyword, "path" => $path, "ivw_keyword" => $ivw_keyword, "ivw_sv" => $ivw_sv );
    }

    public static function buildIVWCode( $node = false )
    {
        $xrowgptINI = eZINI::instance("xrowgpt.ini");
        $string = "";
        if ( $xrowgptINI->variable( 'IVWSettings', 'Enabled' ) == "true" )
        {
            //todo, wo kommt die node her?
            $keyword_info = xrowgpt::getKeyword( $node );
            $string .= '<script type="text/javascript">
                        if (device != "desktop"){
                        <!-- SZM VERSION="2.0" -->
                        var iam_data = {
                        "st": ivw_identifier, // site
                        "cp":"' . $keyword_info["ivw_keyword"] . '_" + ivwletter, // code SZMnG-System 2.0
                        "sv":"mo"
                        }
                        iom.c(iam_data, 1);
                        <!--/SZM -->
                        }else{
                        <!-- SZM VERSION="2.0" -->
                        var iam_data = {
                        "st": ivw_identifier, // site
                        "cp":"' . $keyword_info["ivw_keyword"] . '", // code SZMnG-System 2.0
                        "sv":"' . $keyword_info["ivw_sv"] . '", // i2= FRABO TAG aktiv Async, in= FRABO TAG aktiv   ke= deaktiviert (nur auf der Startseite)
                        "co":"kommentar" // comment
                        }
                        iom.c(iam_data, 1);
                        <!--/SZM -->
                        }
                        </script>';
        }
        return $string;
    }

    public static function buildGPTCode( $keyword = array() )
    {
        $xrowgptINI = eZINI::instance("xrowgpt.ini");
        $oms_code = $xrowgptINI->variable( 'OmsSettings', 'AdServerID' );
        $oms_code_mobile = $xrowgptINI->variable( 'OmsSettings', 'AdServerIDMobile' );
        $site = $xrowgptINI->variable( 'OmsSettings', 'OmsSite' );
        $oms_site_mobile = $xrowgptINI->variable( 'OmsSettings', 'OmsSiteMobile' );
        $string = "";
        $custom_tags = "";
        $path = $keyword["path"];

        if ( empty($path) )
        {
            $custom_tags = 'googletag.pubads().setTargeting("NodeID", "' . $GLOBALS["eZRequestedModuleParams"]['module_name'] . '_' . $GLOBALS["eZRequestedModuleParams"]['function_name'] . '" );';
        }
        else
        {
            $custom_tags = 'googletag.pubads().setTargeting("NodeID", ' . end($path) . ' );';
            foreach( $path as $i => $path_element )
            {
                $custom_tags .= 'googletag.pubads().setTargeting("TreeL'. $i .'", '. $path_element .' );';
                if( $i === 5 )
                {
                    break;
                }
            }
        }

        if( $xrowgptINI->hasVariable( 'GeneralSettings', 'Mode' )  )
        {
            $custom_tags .= "googletag.pubads().setTargeting('key', '". $xrowgptINI->variable( 'GeneralSettings', 'Mode' ) ."' );";
        }

        $string .= '<script type="text/javascript">
         if (device == "mobile"){
            var src = "http://oms.nuggad.net/javascripts/nuggad-ls.js";
            document.write(\'<scr\' + \'ipt src="\' + src + \'"></scr\' + \'ipt>\');
         }</script>
                
        <script type="text/javascript">
            //Synchron Call
            
              (function() {
                var useSSL = "https:" == document.location.protocol;
                var src = (useSSL ? "https:" : "http:") + "//www.googletagservices.com/tag/js/" + googletagservice_file;
                document.write(\'<scr\' + \'ipt src="\' + src + \'"></scr\' + \'ipt>\');
              })();
        </script>

        <script type="text/javascript">
        if (device == "mobile"){
        <!-- nugg.ad mobile call -->

        var oms_site="' . $oms_site_mobile . '";
        var WLRCMD="";
        oms_network="oms"
        var nuggn='.$xrowgptINI->variable( 'OmsSettings', 'Nuggn' ).';
        var nugghost="http://"+oms_network+".nuggad.net";

        <!-- google mobile gpt -->
            nuggad.init({"rptn-url": nugghost}, function(api) {
                api.rc({"nuggn": nuggn});
            });

            //!-- Aufbereitung WLRCMD Variable --
            var NUGGarr=Array();
            if (typeof WLRCMD !=\'undefined\' && WLRCMD !=\'\')
            { arrALL=WLRCMD.split(";");
            for (TUPL in arrALL) {
                if (arrALL[TUPL].indexOf(\'=\') !=-1){
                    NUGGarr[arrALL[TUPL].split(\'=\')[0]]=arrALL[TUPL].split(\'=\')[1];
                }
            }
            }
            //!-- ENDE Aufbereitung WLRCMD Variable --

            if (window.innerWidth >= 340) {
                googletag.cmd.push(function() {
                    googletag.defineSlot(\'/'.$oms_code_mobile.'/\'+oms_site+\'/\'+oms_zone+\'/pos1\',[[320, 50],[2, 1], [3, 1], [4, 1], [6, 1]], \'div-gpt-ad-1363251388018-0\').addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code_mobile.'/\'+oms_site+\'/\'+oms_zone+\'/pos2\',[[320, 50],[2, 1], [3, 1], [4, 1], [6, 1]], \'div-gpt-ad-1363251388018-1\').addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code_mobile.'/\'+oms_site+\'/\'+oms_zone+\'/pos3\',[[320, 50],[2, 1], [3, 1], [4, 1], [6, 1]], \'div-gpt-ad-1363251388018-2\').addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code_mobile.'/\'+oms_site+\'/\'+oms_zone+\'/pos4\',[[320, 50],[2, 1], [3, 1], [4, 1], [6, 1]], \'div-gpt-ad-1363251388018-3\').addService(googletag.pubads());
                    googletag.pubads().setTargeting(\'nielsen\',\'1\');
                    if (typeof NUGGarr !=\'undefined\') {
                        for (var key in NUGGarr) {
                            googletag.pubads().setTargeting(key, NUGGarr[key]);
                        }
                    };

                    ' . $custom_tags . '

                    googletag.enableServices();
                });
            }
        
        <!-- Ende Einbau im Header -->
        }else{
            googletag.cmd.push(function() {
                if (page_width >= 1100) {
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [[120, 600],[160, 600],[200, 600]], "oms_gpt_skyscraper").addService(googletag.pubads());
                }

                if (page_width >= 748) {
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone + oms_zone_addon, [728, 90], "oms_gpt_superbanner").addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [728, 91], "oms_gpt_superbanner1").addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [728, 92], "oms_gpt_superbanner2").addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [728, 93], "oms_gpt_superbanner3").addService(googletag.pubads());
                }
                else if (page_width >= 488) {
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [468, 60], "oms_gpt_fullbanner").addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [468, 61], "oms_gpt_fullbanner1").addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [468, 62], "oms_gpt_fullbanner2").addService(googletag.pubads());
                    googletag.defineSlot(\'/'.$oms_code.'/\'+oms_site+\'/\'+oms_zone, [468, 63], "oms_gpt_fullbanner3").addService(googletag.pubads());
                }
                googletag.pubads().enableSingleRequest();
                googletag.pubads().enableSyncRendering(); // Add sync rendering mode
                ' . $custom_tags . '

                <!-- Hier wird das Bundesland definiert -->

                googletag.enableServices();
                googletag.pubads().setTargeting("bundesland","NI");

                if (typeof WLRCMD !="undefined" && WLRCMD !="")
                {
                    temp=WLRCMD.split(";");
                    for (var id in temp) {
                        if (temp[id].indexOf("=") != -1){
                            values = temp[id].split("=")[1];
                            for (var id2 in temp) {
                                if ((temp[id2].indexOf("=") != -1) && (temp[id].split("=")[0] == temp[id2].split("=")[0]) && (id < id2)){
                                values += ";"+temp[id2].split("=")[1];
                                delete temp[id2];
                                }
                            }
                            temp2 = values.split(";");
                            //console.log(temp[id].split("=")[0]+" "+temp2)
                            //console.log(\"googletag.pubads().setTargeting(\"+temp[id].split("=")[0]+\", \"+temp2+\")\");
                            googletag.pubads().setTargeting(temp[id].split("=")[0], temp2);
                        }
                    }
                }
            });
        }</script>';
        return $string;
    }

    public static function getSettingVariables()
    {
        $string = '<script language="JavaScript" type="text/javascript">';
        $xrowgptINI = eZINI::instance("xrowgpt.ini");
        $bp_settings = $xrowgptINI->group( "BreakPoints" );
        $string .= "var page_width = window.innerWidth;";
        $string .= "var breakpoints = [];";
        
        foreach ( $bp_settings[Breakpoint] as $number => $size)
        {
            $string.= 'breakpoints[' . $number . '] = "' . $size . '";';
        }

        $string .= "var tabletbreakpoint = " . $xrowgptINI->variable( 'BreakPointInfos', 'DesktopToTabletEdge' ) . ";";
        $string .= "var mobilebreakpoint = " . $xrowgptINI->variable( 'BreakPointInfos', 'TabletToMobileEdge' ) . ";";
        $string .= "var device = 'desktop';";
        $string .= "var ivwletter = '';";
        $string .= "var current_breakpoint = '';";
        $string .= "var oms_zone_addon = '';";
        $string .= "var ivw_identifier = '" . $xrowgptINI->variable( 'IVWSettings', 'Identifier' ) . "';";
        $string .= "if (page_width < 1100){oms_zone_addon = '_nowp';}";
        
        $string .= "
                for (i = 1; i < breakpoints.length; i++) {
                    if ( page_width < breakpoints[i] )
                    {
                        var current_breakpoint = i;
                        if ( current_breakpoint <=  tabletbreakpoint)
                        {
                            var device = 'tablet';
                            var ivwletter = 't';
                        }
                        if ( current_breakpoint <=  mobilebreakpoint)
                        {
                            var device = 'mobile';
                            var ivwletter = 'm';
                            var ivw_identifier = '" . $xrowgptINI->variable( 'IVWSettings', 'IdentifierMobile' ) . "';
                        }
                        break;
                    }
                }";
         $string .= "var googletagservice_file = 'gpt.js';";
         $string .= "if(device == 'mobile'){var googletagservice_file = 'gpt_mobile.js';}";
         $string .= "</script>";
        return $string;
    }

    public static function buildHeaderCode( $node = false  )
    {
        $xrowgptINI = eZINI::instance("xrowgpt.ini");
        $show_ads = xrowgpt::checkDisplayStatus();

        $string .= xrowgpt::getSettingVariables();

        //add ivw script when module is activated
        if ( $xrowgptINI->variable( 'IVWSettings', 'Enabled' ) == "true" )
        {
            $string .= '<script type="text/javascript" src="https://script.ioam.de/iam.js"></script>';
        }

        //add oms stuff when ads are displayed
        if( $show_ads )
        {
            $keyword_info = xrowgpt::getKeyword( $node );
            $string .= '<script language="JavaScript" type="text/javascript">
                        var oms_site = "' . $xrowgptINI->variable( 'OmsSettings', 'OmsSite' ) . '";
                        var oms_zone = "' . $keyword_info["keyword"] . '";
                        </script>
                        <script type="text/javascript" src="/extension/xrowgpt/design/xrowgpt/javascript/omsvjs14_1.js"></script>
                        <script>
                        try
                        {
                            var ystr="";
                                var y_adj="";
                        
                            for (var id in yl.YpResult.getAll()) {
                                c = yl.YpResult.get(id);
                                ystr+= \';y_ad=\'+c.id;
                                if(c.format){
                                    y_adj=\';y_adj=\'+c.format;
                                }
                            }
                            ystr+=y_adj+\';\';
                            WLRCMD=WLRCMD+ystr+segQS+crtg_content;
                        }
                        catch(err)
                        {}
                        </script>';

            $string .= xrowgpt::buildGPTCode($keyword_info);
        }

        return $string;
    }
}