<?php
//IMathAS: LTI instructor home page
//(c) 2020 David Lippman

use \IMSGlobal\LTI;

require("../init.php");
if (!isset($_SESSION['ltirole']) || $_SESSION['ltirole']!='instructor') {
	echo _("Not authorized to view this page");
	exit;
}

if (!isset($_GET['launchid'])) {
    // this should have gotten inserted by general.js out of sessionstorage 
    echo _('Missing launch id');
    exit;
}

require_once(__DIR__ . '/lib/lti.php');
require_once __DIR__ . '/Database.php';

//Look to see if a hook file is defined, and include if it is
if (isset($CFG['hooks']['ltihome'])) {
	require($CFG['hooks']['ltihome']);
}

$db = new Imathas_LTI_Database($DBH);
$launch = LTI\LTI_Message_Launch::from_cache($_GET['launchid'], $db);
$contextid = $launch->get_platform_context_id();
$platform_id = $launch->get_platform_id();
$resource_link = $launch->get_resource_link();
$link = $db->get_link_assoc($resource_link['id'], $contextid, $platform_id);
$localcourse = $db->get_local_course($contextid, $platform_id);

//HTML Output
$pagetitle = "LTI Home";
require("../header.php");

if ($link->get_placementtype() == 'course') {
    $cid = $link->get_typeid();
	echo '<h2>'._('LTI Placement of whole course').'</h2>';
    echo "<p><a href=\"../course/course.php?cid=" . Sanitize::courseId($cid) . "\">"._("Enter course")."</a></p>";
    
} else if ($link->get_placementtype() == 'assess') {
    $typeid = $link->get_typeid();
	$stm = $DBH->prepare("SELECT name,avail,startdate,enddate,date_by_lti,ver,courseid FROM imas_assessments WHERE id=:id");
	$stm->execute(array(':id'=>$typeid));
    $line = $stm->fetch(PDO::FETCH_ASSOC);
    $cid = $line['courseid'];
	echo "<h2>".sprintf(_("LTI Placement of %s"), Sanitize::encodeStringForDisplay($line['name'])) . "</h2>";
	if ($line['ver'] > 1) {
		echo "<p><a href=\"../assess2/?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "\">"._("Preview assessment")."</a> | ";
		echo "<a href=\"../course/isolateassessgrade.php?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "\">"._("Grade list")."</a> ";
		echo "| <a href=\"../course/gb-itemanalysis2.php?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "\">"._("Item Analysis")."</a>";
	} else {
		echo "<p><a href=\"../assessment/showtest.php?cid=" . Sanitize::courseId($cid) . "&id=" . Sanitize::encodeUrlParam($typeid) . "\">"._("Preview assessment")."</a> | ";
		echo "<a href=\"../course/isolateassessgrade.php?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "\">"._("Grade list")."</a> ";
		echo "| <a href=\"../course/gb-itemanalysis.php?cid=" . Sanitize::courseId($cid) . "&asid=average&aid=" . Sanitize::encodeUrlParam($typeid) . "\">"._("Item Analysis")."</a>";
	}
	echo "</p>";

	$now = time();
	echo '<p>';
	if ($line['avail']==0) {
		echo _('Currently unavailable to students.');
	} else if ($line['date_by_lti']==1) {
		echo _('Waiting for the LMS to send a date');
	} else if ($line['date_by_lti']>1) {
		echo sprintf(_('Default due date set by LMS. Available until: %s.'),formatdate($line['enddate']));
		echo '</p><p>';
		if ($line['date_by_lti']==2) {
			echo _('This default due date was set by the date reported by the LMS in your instructor launch, and may change when the first student launches the assignment. ');
		} else {
			echo _('This default due date was set by the first student launch. ');
		}
		echo _('Be aware some LMSs will send unexpected dates on instructor launches, so don\'t worry if the date shown in the assessment preview is different than you expected or different than the default due date. ');
		echo '</p><p>';
		echo _('If the LMS reports a different due date for an individual student when they open this assignment, this system will handle that by setting a due date exception. ');
	} else if ($line['avail']==1 && $line['startdate']<$now && $line['enddate']>$now) { //regular show
		echo _("Currently available to students.")."  ";
		echo sprintf(_("Available until %s"), formatdate($line['enddate']));
	} else {
		echo sprintf(_('Currently unavailable to students. Available %s until %s'),formatdate($line['startdate']),formatdate($line['enddate']));
	}
	echo '</p>';

    if ($line['ver']>1) {
        $addassess = 'addassessment2.php';
    } else {
        $addassess = 'addassessment.php';
    }
    echo "<p><a href=\"../course/$addassess?cid=" . Sanitize::courseId($cid) . "&id=" . Sanitize::encodeUrlParam($typeid) . "&from=lti\">"._("Settings")."</a> | ";
    echo "<a href=\"../course/addquestions.php?cid=" . Sanitize::courseId($cid) . "&aid=" . Sanitize::encodeUrlParam($typeid) . "&from=lti\">"._("Questions")."</a></p>";

    echo '<p>&nbsp;</p><p class=small>'.sprintf(_('This assessment is housed in course ID %s'),Sanitize::courseId($cid)).'</p>';
    echo '<p class=small>';
    if ($db->has_lineitem($link, $localcourse)) {
        echo _('Has lineitem for passing back grades.');
    } else if ($launch->can_create_lineitem()) {
        echo _('Does not currently have a lineitem for passing back grades, but LMS supports creating lineitems.');
    } else {
        echo _('Does not currently have a lineitem for passing back grades, and LMS does not support adding one.');
    }
    echo '</p>';
}
require("../footer.php");

function formatdate($date) {
	if ($date==0 || $date==2000000000) {
		return 'Always';
	} else {
		return tzdate("D n/j/y, g:i a",$date);
	}
}

?>
