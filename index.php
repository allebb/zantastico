<?php
/**
 * ZANTASTICO
 * ========
 * Zantastico is a free module (GPL) developed by Bobby Allen to deploy open-source web scripts to user hosting accounts on ZPanel.
 * For help and support please visit the ZPanel forums at: http://forums.zpanelcp.com/
 *
 */
include('conf/zcnf.php');
include('lang/' . GetPrefdLang($personalinfo['ap_language_vc']) . '.php');
include('inc/zAccountDetails.php');
include 'modules/advanced/zantastico/lib/xmlparser.php';
$packages_xml = file_get_contents('modules/advanced/zantastico/packages/packages.xml');
$packages = new XMLParser($packages_xml);
$packages->Parse();

function rec_copy($to_path, $from_path) {
    $to_path = strtolower(str_replace(" ", "", $to_path));
    mkdir($to_path, 0777, true);
    $this_path = getcwd();
    if (is_dir($from_path)) {
        chdir($from_path);
        $handle = opendir('.');
        while (($file = readdir($handle)) !== false) {
            if (($file != ".") && ($file != "..")) {
                if (is_dir($file)) {
                    rec_copy($to_path . '/' . $file . "/", $from_path . '/' . $file . "/");
                    chdir($from_path);
                }
                if (is_file($file)) {
                    copy($from_path . '/' . $file, $to_path . '/' . $file);
                }
            }
        }
        closedir($handle);
    }
}

if (isset($_POST['btDeploy'])) {
    foreach ($packages->document->package as $package) {
        if ($package->name[0]->tagData == $_GET['pkg']) {
            $deploy_name = $package->name[0]->tagData;
            $deploy_installer = $package->installer[0]->tagData;
            $deploy_version = $package->version[0]->tagData;
        }
    }
    // Get the full path to the users (without trailing slash!)
    $path_to_home = GetSystemOption('hosted_dir') . $useraccount['ac_user_vc'];
    // Lets get the domain details from the database!
    $sql = "SELECT * FROM z_vhosts WHERE vh_name_vc='" . $_POST['inDomain'] . "' AND vh_deleted_ts IS NULL";
    $listdomain = DataExchange("r", $z_db_name, $sql);
    $rowdomain = mysql_fetch_assoc($listdomain);
    $totaldomain = mysql_num_rows($listdomain);
    $domain_path = $rowdomain['vh_directory_vc'];
    $path_to_deploy = $path_to_home . '/' . $domain_path . '/' . $_POST['inFolder'] . '';
    $path_to_package = GetSystemOption('zpanel_root') . 'modules/advanced/zantastico/packages/' . strtolower($deploy_name) . '/';
    // Check that the deployment folder does not already exist!
    if ($totaldomain < 1) {
        echo '<div class="zannouce">Domain error!</div><br><br>The domain does not exist, you must choose a domain to deploy to! Please <a href="javascript:history.back(1)">go back</a> and choose a domain!';
    } elseif ((file_exists($path_to_deploy)) && ($_POST['inFolder'] <> "/")) {
        echo '<div class="zannouce">Destination already exists!</div><br><br>Sorry, the deployment folder (<strong>' . $domain_path . '/' . strtolower($_POST['inFolder']) . '</strong>) already exists, please <a href="javascript:history.back(1)">go back</a> and choose a new folder!';
    } else {
        // Create the new folder and chmod it and copy the package data to it!
        rec_copy($path_to_deploy, $path_to_package);
        // Redirect to the new installer
        echo "
			<div class=\"zannouce\">Your package is now ready!</div><br><br>Congratulations! <strong>" . $deploy_name . "</strong> has now been deployed! Click here to be taken to the <a href=\"http://" . $rowdomain['vh_name_vc'] . "/" . strtolower($_POST['inFolder']) . "\" target=\"_blank\">installer</a>!
			<br><br><p><a href=\"/index.php?c=advanced&p=zantastico\">Deploy another package</a></p>";
        // Done!
    }
} elseif (isset($_GET['next'])) {
    foreach ($packages->document->package as $package) {
        if ($package->name[0]->tagData == $_GET['pkg']) {
            $deploy_name = $package->name[0]->tagData;
            $deploy_installer = $package->installer[0]->tagData;
            $deploy_version = $package->version[0]->tagData;
        }
    }
    // Display the deployment form!
    $sql = "SELECT * FROM z_vhosts WHERE vh_acc_fk=" . $useraccount['ac_id_pk'] . " AND vh_deleted_ts IS NULL";
    $listdomains = DataExchange("r", $z_db_name, $sql);
    $rowdomains = mysql_fetch_assoc($listdomains);
    $totaldomains = DataExchange("t", $z_db_name, $sql);

    // Make sure a domain has been setup on the account before continuing
    if ($totaldomains <> 0) {
        echo "<h2>Where to install?</h2>
		<p>Please choose a domain and new directory to where you would like <strong>" . $deploy_name . "</strong> to be deployed to..</p><br><br>
				<form id=\"frmDeploy\" name=\"frmDeploy\" method=\"post\" action=\"/index.php?c=advanced&p=zantastico&pkg=" . $deploy_name . "\">
				  <table class=\"zgrid\">
					<tr>
					  <th>Deploy to: </th>
					  <td><label for=\"inDomain\"></label>
						<select name=\"inDomain\" id=\"inDomain\">
						";
        // Get all the domains and list them!
        do {
            echo "<option value=\"" . $rowdomains['vh_name_vc'] . "\">" . $rowdomains['vh_name_vc'] . "</option>";
        } while ($rowdomains = mysql_fetch_assoc($listdomains));
        echo "</select></td>
					  <td><label for=\"inFolder\"></label>
					  <input type=\"text\" name=\"inFolder\" id=\"inFolder\" value=\"" . strtolower($deploy_name) . "/\" /></td>
					  <td><input type=\"submit\" name=\"btDeploy\" id=\"btDeploy\" value=\"Deploy!\"/></td>
					</tr>
				  </table>
				</form>";
    } else {
        // No domain found, we direct them to create one.
        echo "<h2>Problem with installation...</h2>
		<p>There are no domains on your account.  Please add a domain first before deploying a software package.</p>
		<p><a href=\"/index.php?c=advanced&p=zantastico\">Back</a></p>";
    }
} else {
    echo "<h2>Package selection..</h2>
		<p>Zantastico is an open-source package deployment tool which quickly and effeciently will copy a wide range of open-source web scripts to your hosting enviroment and then re-direct you to the installer to enable you to personalise your setup!</p>
		<p>By using Zantastico you save time and bandwidth by not needing to upload large web scripts to your hosting envioment.</p>
		<br><br>
		<table class=\"zgrid\">
		  <tr>
			<th>Package</th>
			<th>Type</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
		  </tr>";
    foreach ($packages->document->package as $package) {
        echo "<tr>
			<th>" . $package->name[0]->tagData . " (" . $package->version[0]->tagData . ")</th>
			<td>" . $package->type[0]->tagData . "</td>
			<td>&nbsp;</td>
			<td><a href=\"/index.php?c=advanced&p=zantastico&pkg=" . $package->name[0]->tagData . "&next\">Deploy!</a></td>
		  </tr>";
    }
    echo "</table>";
}
?>
<!--Spinner DIV styles-->
<style type="text/css" media="screen"> 
    #processing_overlay
    {
        position: fixed;
        z-index: 1;
        top: 0px;
        left: 0px;
        width: 100%;
        height: 100%;
        background-color:#000;
        opacity:0.5;
        cursor:wait;
    }						
    #processing_container {
        position:relative;
        z-index: 10;
        font-family: Arial, sans-serif;
        font-size: 12px;
        width:300px;
        min-width: 200px;
        max-width: 600px;
        background: #FFF;
        border: solid 5px #0C98C8;
        color: #000;
        -moz-border-radius: 5px;
        -webkit-border-radius: 5px;
        border-radius: 5px;
        margin-top:50px;
        margin-right:50%;
        margin-left:50%;
    }
    #processing_title {
        font-size: 14px;
        font-weight: bold;
        text-align: center;
        line-height: 1.75em;
        background-color:#0C98C8;
        color: #FFF;
        border-bottom: solid 1px #999;
        cursor: default;
        padding: 0em;
        margin: 0em;
    }
    #processing_content {
        text-align: center;
        padding: 1em 1.75em;
        margin: 0em;
    }
    #processing_message {
        text-align:center;
        vertical-align:middle;
    }
</style> 

<!--This function SHOULD check if jquery is loaded, and if not then load as needed-->
<script type="text/javascript">
    if (typeof jQuery == 'undefined') { 
        var head = document.getElementsByTagName("head")[0];
        script = document.createElement('script');
        script.id = 'jQuery';
        script.type = 'text/javascript';
        script.src = 'modules/advanced/zantastico/lib/jquery.js';
        head.appendChild(script);
    }
</script>

<!--Load spinner when deploy button pressed-->
<script type="text/javascript">
    $(document).ready(function(){
        $('#btDeploy').click(function() {
            $('#processing_overlay').show();
            $('#processing_container').show();
        });
    });
</script>

<!--Spinner DIVS-->
<div id="processing_overlay" style="display:none;"> </div>

<div id="processing_container" style="display:none;">
    <h1 id="processing_title">Zantastico is busy...</h1>
    <div id="processing_content">
        <div id="processing_message">Please wait while Zantastico deploys your choosen script/application to your web hosting space...<br/><br/><?php if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) { ?>
                <marquee behavior="alternate" scrollamount="20"><img src="/modules/advanced/zantastico/images/ajax-loader.gif" /></marquee>
            <?php } else { ?>
                <img src="/modules/advanced/zantastico/images/ajax-loader.gif" />
            <?php } ?>
        </div>
    </div>
</div>