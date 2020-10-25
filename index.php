<?php
/**
 * SourceForge User's Personal Page
 *
 * SourceForge: Breaking Down the Barriers to Open Source Development
 * Copyright 1999-2001 (c) VA Linux Systems
 * http://sourceforge.net
 *
 * @version   $Id: index.php,v 1.18 2004/07/19 22:48:17 jcox Exp $
 */
require_once '../../mainfile.php';

$langfile = 'my.php';

require_once XOOPS_ROOT_PATH . '/modules/xfmod/include/pre.php';
require_once XOOPS_ROOT_PATH . '/modules/xfaccount/account_util.php';
require_once XOOPS_ROOT_PATH . '/modules/xfmod/include/vote_function.php';
require_once XOOPS_ROOT_PATH . '/modules/xfmod/maillist/maillist_utils.php';
$GLOBALS['xoopsOption']['template_main'] = 'xfaccount_index.html';
if (!$xoopsUser) {
    redirect_header(XOOPS_URL . '/user.php?xoops_redirect=/modules/xfaccount/', 1, _NOPERM);

    exit;
}

$metaTitle = ': ' . _XF_MY_MYPERSONALPAGE;

include '../../header.php';
$xoopsTpl->assign('account_header', account_header($xoopsUser->uname() . "'s Account"));

// Determine whether this user is a project or community admin.
// This is the same query used to render the "My Projects" box.
$myprojects_sql = 'SELECT g.group_name,g.group_id,g.unix_group_name,g.status,g.type,ug.admin_flags,g.is_public '
                  . ' FROM '
                  . $xoopsDB->prefix('xf_groups')
                  . ' g,'
                  . $xoopsDB->prefix('xf_user_group')
                  . ' ug '
                  . ' WHERE g.group_id=ug.group_id '
                  . " AND ug.user_id='"
                  . $xoopsUser->getVar('uid')
                  . "' "
                  . " AND g.status='A'"
                  . ' ORDER BY g.group_name';

$myprojects_result = $xoopsDB->query($myprojects_sql);
$myprojects_rows = $xoopsDB->getRowsNum($myprojects_result);

/***** PROJECT LIST *****/
$status = [
    'I' => '(Incomplete)',
    'A' => '',
    'N' => '(Inactive)',
    'P' => '(Pending)',
    'H' => '(Holding)',
    'D' => '(Deleted)',
];

if (!$myprojects_result || $myprojects_rows < 1) {
    $xoopsTpl->assign('no_projects', true);

    $xoopsTpl->assign('prj_comm_block_title', _XF_MY_MYPROJECTS);

    $xoopsTpl->assign('prj_comm_content', _XF_MY_NOPROJECTS . '<br><br>');
} else {
    $has_projects = 0;

    $has_communities = 0;

    for ($i = 0; $i < $myprojects_rows; $i++) {
        $pl = $xoopsDB->fetchArray($myprojects_result);

        $pl['status'] = $status[$pl['status']];

        if (!$pl['is_public']) {
            $pl['status'] = '(Private)';
        }

        $prj_list[$i] = $pl;

        if (2 == $prj_list[$i]['type']) {
            $has_communities = 1;
        } else {
            $has_projects = 1;
        }
    }

    $xoopsTpl->assign('prj_list', $prj_list);

    if ($has_projects) {
        if ($has_communities) {
            $xoopsTpl->assign('prj_comm_block_title', _XF_MY_MYPRJCOMM);
        } else {
            $xoopsTpl->assign('prj_comm_block_title', _XF_MY_MYPROJECTS);
        }
    } else {
        $xoopsTpl->assign('prj_comm_block_title', _XF_MY_MYCOMM);
    }
}

//This is extra information that we need later.
$is_pa = false;
$is_ca = false;
if ($myprojects_rows) {
    // This user might be a project or community admin.

    for ($i = 0; $i < $myprojects_rows; $i++) {
        if (mb_stristr(unofficial_getDBREsult($myprojects_result, $i, 'admin_flags'), 'A')) {
            if (2 == unofficial_getDBResult($myprojects_result, $i, 'type')) {
                $is_ca = true;
            } else {
                $is_pa = true;
            }
        }
    }
}

/***** ASSIGNED ITEMS *****/
$xoopsTpl->assign('assigned_items_title', _XF_MY_MYASSIGNEDITEMS);
$sql = 'SELECT g.group_name,agl.name,agl.group_id,a.group_artifact_id,a.assigned_to,a.summary,a.artifact_id,a.priority '
       . 'FROM '
       . $xoopsDB->prefix('xf_artifact')
       . ' a, '
       . $xoopsDB->prefix('xf_groups')
       . ' g, '
       . $xoopsDB->prefix('xf_artifact_group_list')
       . ' agl '
       . 'WHERE a.group_artifact_id=agl.group_artifact_id '
       . 'AND agl.group_id=g.group_id '
       . "AND g.status = 'A' "
       . "AND a.assigned_to='"
       . $xoopsUser->getVar('uid')
       . "' "
       . "AND a.status_id='1' "
       . 'ORDER BY agl.group_id,a.group_artifact_id,a.assigned_to,a.status_id';

$result = $xoopsDB->query($sql);
$rows = $xoopsDB->getRowsNum($result);
if (!$result || $rows < 1) {
    $xoopsTpl->assign('assigned_items_content', "<tr><td colspan='2'>" . _XF_MY_NOOPENTRACKERITEMS . '<br><br></td></tr>');
} else {
    $content = '';

    $last_group = 0;

    for ($i = 0; $i < $rows; $i++) {
        if (unofficial_getDBResult($result, $i, 'group_artifact_id') != $last_group) {
            $content .= "<tr><td colspan=2><b><a href='../xfmod/tracker/?group_id="
                        . unofficial_getDBResult($result, $i, 'group_id')
                        . '&atid='
                        . unofficial_getDBResult($result, $i, 'group_artifact_id')
                        . "'>"
                        . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'group_name'))
                        . ' - '
                        . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'name'))
                        . '</a></td></tr>';
        }

        $content .= "<tr bgcolor='"
                       . get_priority_color(unofficial_getDBResult($result, $i, 'priority'))
                       . "'>"
                       . "<td><a href='../xfmod/tracker/?func=detail&aid="
                       . unofficial_getDBResult($result, $i, 'artifact_id')
                       . '&group_id='
                       . unofficial_getDBResult($result, $i, 'group_id')
                       . '&atid='
                       . unofficial_getDBResult($result, $i, 'group_artifact_id')
                       . "'>"
                       . unofficial_getDBResult($result, $i, 'artifact_id')
                       . '</td>'
                       . '<td width="99%">'
                       . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'summary'))
                       . '</td></tr>';

        $last_group = unofficial_getDBResult($result, $i, 'group_artifact_id');
    }

    $xoopsTpl->assign('assigned_items_content', $content);
}

/***** SUBMITTED ITEMS *****/
$xoopsTpl->assign('submitted_items_title', _XF_MY_MYSUBMITTEDITEMS);
$sql = 'SELECT g.group_name,agl.name,agl.group_id,a.group_artifact_id,a.assigned_to,a.summary,a.artifact_id,a.priority '
       . 'FROM '
       . $xoopsDB->prefix('xf_artifact')
       . ' a, '
       . $xoopsDB->prefix('xf_groups')
       . ' g, '
       . $xoopsDB->prefix('xf_artifact_group_list')
       . ' agl '
       . 'WHERE a.group_artifact_id=agl.group_artifact_id '
       . 'AND agl.group_id=g.group_id '
       . "AND g.status = 'A' "
       . "AND a.submitted_by='"
       . $xoopsUser->getVar('uid')
       . "' "
       . "AND a.status_id='1' "
       . 'ORDER BY agl.group_id,a.group_artifact_id,a.submitted_by,a.status_id';

$result = $xoopsDB->query($sql);
$rows = $xoopsDB->getRowsNum($result);
if (!$result || $rows < 1) {
    $xoopsTpl->assign('submitted_items_content', "<tr><td colspan='2'>" . _XF_MY_NOSUBMITTEDTRACKERITEMS . '<br><br></td></tr>');
} else {
    $content = '';

    $last_group = 0;

    for ($i = 0; $i < $rows; $i++) {
        if (unofficial_getDBResult($result, $i, 'group_artifact_id') != $last_group) {
            $content .= "<tr><td colspan=2><b><a href='../xfmod/tracker/?group_id="
                        . unofficial_getDBResult($result, $i, 'group_id')
                        . '&atid='
                        . unofficial_getDBResult($result, $i, 'group_artifact_id')
                        . "'>"
                        . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'group_name'))
                        . ' - '
                        . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'name'))
                        . '</a></td></tr>';
        }

        $content .= "<tr bgcolor='"
                       . get_priority_color(unofficial_getDBResult($result, $i, 'priority'))
                       . "'>"
                       . "<td><a href='../xfmod/tracker/?func=detail&aid="
                       . unofficial_getDBResult($result, $i, 'artifact_id')
                       . '&group_id='
                       . unofficial_getDBResult($result, $i, 'group_id')
                       . '&atid='
                       . unofficial_getDBResult($result, $i, 'group_artifact_id')
                       . "'>"
                       . unofficial_getDBResult($result, $i, 'artifact_id')
                       . '</td>'
                       . '<td width="99%">'
                       . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'summary'))
                       . '</td></tr>';

        $last_group = unofficial_getDBResult($result, $i, 'group_artifact_id');
    }

    $xoopsTpl->assign('submitted_items_content', $content);
}

/***** MONITORED FORUMS *****/
/*
$xoopsTpl -> assign("forums_title", _XF_MY_MONITOREDFORUMS);
$sql = "SELECT g.group_name,g.group_id,fgl.group_forum_id,fgl.forum_name "."FROM ".$xoopsDB -> prefix("xf_groups")." g,".$xoopsDB -> prefix("xf_forum_group_list")." fgl,".$xoopsDB -> prefix("xf_forum_monitored_forums")." fmf "."WHERE g.group_id=fgl.group_id "."AND g.status='A' "."AND fgl.group_forum_id=fmf.forum_id "."AND fmf.user_id='".$xoopsUser -> getVar("uid")."' ORDER BY group_name DESC";

$result = $xoopsDB -> query($sql);
$rows = $xoopsDB -> getRowsNum($result);
if (!$result || $rows < 1)
{
    $xoopsTpl -> assign("forums_content", "<tr><td colspan='2'>"._XF_MY_NOTMONITORFORUMS."<br><br></td></tr>");
}
else
{
    $last_group = 0;
    $content = "";
    for ($i = 0; $i < $rows; $i ++)
    {
        if (unofficial_getDBResult($result, $i, 'group_id') != $last_group)
        {
            //class='". ($i % 2 != 0 ? "bg2" : "bg3")."'
            $content.= "<tr><td colspan='2'><b><a href='../xfmod/forum/?group_id=".unofficial_getDBResult($result, $i, 'group_id')."'>".$ts -> makeTboxData4Show(unofficial_getDBResult($result, $i, 'group_name'))."</a></td></tr>";
        }
        $content.= "<tr><td align='middle'><a href='../xfmod/forum/monitor.php?forum_id=".unofficial_getDBResult($result, $i, 'group_forum_id')."'><img src='../xfmod/images/ic/trash.png' height='16' width='16' border='0' alt='remove monitor'></a></td>"."<td width='99%'><a href='../xfmod/forum/forum.php?forum_id=".unofficial_getDBResult($result, $i, 'group_forum_id')."'>".$ts -> makeTboxData4Show(unofficial_getDBResult($result, $i, 'forum_name'))."</a></td></tr>";
        $last_group = unofficial_getDBResult($result, $i, 'group_id');
    }
    $xoopsTpl -> assign("forums_content", $content);
}
*/
/***** MONITORED FILE MODULES *****/
$xoopsTpl->assign('files_title', _XF_MY_MONITOREDFILES);
$sql = 'SELECT g.group_name,g.unix_group_name,g.group_id,p.name,f.filemodule_id '
       . 'FROM '
       . $xoopsDB->prefix('xf_groups')
       . ' g,'
       . $xoopsDB->prefix('xf_filemodule_monitor')
       . ' f,'
       . $xoopsDB->prefix('xf_frs_package')
       . ' p '
       . "WHERE g.group_id=p.group_id AND g.status = 'A' "
       . 'AND p.package_id=f.filemodule_id '
       . "AND f.user_id='"
       . $xoopsUser->getVar('uid')
       . "' ORDER BY group_name DESC";

$result = $xoopsDB->query($sql);
$rows = $xoopsDB->getRowsNum($result);
if (!$result || $rows < 1) {
    $xoopsTpl->assign('files_content', "<tr><td colspan='2'>" . _XF_MY_NOTMONITORFILES . '<br><br></td></tr>');
} else {
    $last_group = 0;

    $content = '';

    for ($i = 0; $i < $rows; $i++) {
        if (unofficial_getDBResult($result, $i, 'group_id') != $last_group) {
            $content .= "<tr><td colspan='2'><b><a href='../xfmod/project/?" . unofficial_getDBResult($result, $i, 'unix_group_name') . "'>" . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'group_name')) . '</a></td></tr>';
        }

        $content .= "<tr><td align='middle'><a href='../xfmod/project/filemodule_monitor.php?filemodule_id="
                       . unofficial_getDBResult($result, $i, 'filemodule_id')
                       . "'><img src='../xfmod/images/ic/trash.png' height='16' width='16' border='0' alt='remove monitor'></a></td>"
                       . "<td width='99%'><A HREF='../xfmod/project/showfiles.php?group_id="
                       . unofficial_getDBResult($result, $i, 'group_id')
                       . "'>"
                       . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'name'))
                       . '</a></td></tr>';

        $last_group = unofficial_getDBResult($result, $i, 'group_id');
    }

    $xoopsTpl->assign('files_content', $content);
}

/***** MY TASKS *****/
$xoopsTpl->assign('tasks_title', _XF_MY_MYTASKS);
$sql = 'SELECT g.group_name,pgl.project_name,pgl.group_id,pt.group_project_id,pt.priority,pt.project_task_id,pt.summary,pt.percent_complete '
       . 'FROM '
       . $xoopsDB->prefix('xf_groups')
       . ' g,'
       . $xoopsDB->prefix('xf_project_group_list')
       . ' pgl,'
       . $xoopsDB->prefix('xf_project_task')
       . ' pt,'
       . $xoopsDB->prefix('xf_project_assigned_to')
       . ' pat '
       . 'WHERE pt.project_task_id=pat.project_task_id '
       . "AND pat.assigned_to_id='"
       . $xoopsUser->getVar('uid')
       . "' "
       . "AND pt.status_id='1' "
       . 'AND pgl.group_id=g.group_id '
       . 'AND pgl.group_project_id=pt.group_project_id '
       . "AND g.status = 'A'"
       . 'ORDER BY group_name,project_name';

$result = $xoopsDB->query($sql);
$rows = $xoopsDB->getRowsNum($result);
if (!$result || $rows < 1) {
    $xoopsTpl->assign('tasks_content', "<tr><td colspan='2'>" . _XF_MY_NOOPENTASKS . '<br><br></td></tr>');
} else {
    $last_group = 0;

    $content = '';

    for ($i = 0; $i < $rows; $i++) {
        /* Deduce summary style */

        $style_begin = '';

        $style_end = '';

        if (100 == unofficial_getDBResult($result, $i, 'percent_complete')) {
            $style_begin = '<u>';

            $style_end = '</u>';
        }

        if (unofficial_getDBResult($result, $i, 'group_project_id') != $last_group) {
            $content .= "<tr><td colspan='2'><b><a href='../xfmod/pm/task.php?group_id=" . unofficial_getDBResult($result, $i, 'group_id') . '&group_project_id=' . unofficial_getDBResult($result, $i, 'group_project_id') . "'>" . $ts->htmlSpecialChars(
                unofficial_getDBResult($result, $i, 'group_name')
            ) . ' - ' . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'project_name')) . '</a></td></tr>';
        }

        $content .= "<tr bgcolor='"
                    . get_priority_color(unofficial_getDBResult($result, $i, 'priority'))
                    . "'>"
                    . "<td><a href='../xfmod/pm/task.php?func=detailtask&project_task_id="
                    . unofficial_getDBResult($result, $i, 'project_task_id')
                    . '&group_id='
                    . unofficial_getDBResult(
                        $result,
                        $i,
                        'group_id'
                    )
                    . '&group_project_id='
                    . unofficial_getDBResult($result, $i, 'group_project_id')
                    . "'>"
                    . unofficial_getDBResult($result, $i, 'project_task_id')
                    . '</td>'
                    . '<td width="99%">'
                    . $style_begin
                    . $ts->htmlSpecialChars(unofficial_getDBResult($result, $i, 'summary'))
                    . $style_end
                    . '</td></tr>';

        $last_group = unofficial_getDBResult($result, $i, 'group_project_id');
    }

    $xoopsTpl->assign('tasks_content', $content);
}

/***** DEVELOPER SURVEYS *****
 * NOTE: This section needs to be updated manually to display any given survey. */
if (100 != (int)$xoopsForge['devsurvey']) {
    $xoopsTpl->assign('display_survey', true);

    $xoopsTpl->assign('survey_title', _XF_MY_QUICKSURVEY);

    $sql = 'SELECT * ' . 'FROM ' . $xoopsDB->prefix('xf_survey_responses') . ' ' . "WHERE survey_id='" . $xoopsForge['devsurvey'] . "' " . "AND user_id='" . $xoopsUser->getVar('uid') . "' " . "AND group_id='1'";

    $result = $xoopsDB->query($sql);

    if ($xoopsDB->getRowsNum($result) < 1) {
        /* Hasn't taken dev survery yet, so show it */

        $xoopsTpl->assign('survey_content', show_survey(1, $xoopsForge['devsurvey']));
    } else {
        /* User has already taken the developer survery */

        $xoopsTpl->assign('survey_content', "<tr><td colspan='2'>" . _XF_MY_QUICKSURVEYTAKEN . '</td></tr>');
    }
}

/***** BOOKMARKS *****/
$xoopsTpl->assign('bookmarks_title', _XF_MY_MYBOOKMARKS);
$xoopsTpl->assign('bookmarks_none', _XF_MY_NOBOOKMARKS);
$xoopsTpl->assign('bookmarks_add', _XF_MY_ADDBOOKMARK);
$sql = 'SELECT bookmark_url,bookmark_title,bookmark_id FROM ' . $xoopsDB->prefix('xf_user_bookmarks') . " WHERE user_id='" . $xoopsUser->getVar('uid') . "' ORDER BY bookmark_title";

$result = $xoopsDB->query($sql);
$rowCount = $xoopsDB->getRowsNum($result);
$xoopsTpl->assign('bookmarks_count', $rowCount);

$rowList = [];
for ($i = 0; $i < $rowCount; $i++) {
    $rowList[$i] = $xoopsDB->fetchArray($result);
}
$xoopsTpl->assign('bookmarks_list', $rowList);

/***** SITE MAILING LIST PROCESSING *****/
$subscribe_result = '';

if (isset($_POST['list_sub_form_submit'])) {
    $list_sub_form_submit = $_POST['list_sub_form_submit'];
} elseif (isset($_GET['list_sub_form_submit'])) {
    $list_sub_form_submit = $_GET['list_sub_form_submit'];
} else {
    $list_sub_form_submit = null;
}

if (_XF_G_SUBMIT == $list_sub_form_submit) {
    foreach ($_POST as $name => $value) {
        $len = mb_strlen($name);

        if ($len >= 5 && 0 == strcmp(mb_substr($name, $len - 7), '_listid')) {
            $listname = mb_substr($name, 0, $len - 7);

            $sub = $_POST[$listname . '_subscribe'];

            $unsub = $_POST[$listname . '_unsubscribe'];

            $listid = $_POST[$listname . '_listid'];

            $pwd = $_POST[$listname . '_pwd'];

            if ('on' == $sub) {
                $confpwd = $_POST[$listname . '_confpwd'];

                if (0 != mb_strlen($listname) && 0 != mb_strlen($listid) && 0 != mb_strlen($email) && 0 != mb_strlen($pwd) && 0 != mb_strlen($confpwd)) {
                    if (maillist_subscribe($xoopsUser, $xoopsDB, $_SERVER['HTTP_HOST'], $listname, $listid, urldecode($email), $pwd)) {
                        $subscribe_result .= _XF_MY_SUB_SUCCESS . "<br>\n";
                    } else {
                        $subscribe_result .= _XF_MY_SUB_FAIL . "<br>\n";
                    }
                } else {
                    $subscribe_result .= _XF_MY_NOSUB_NODATA . "<br>\n";
                }
            }

            if ('on' == $unsub) {
                if (0 != mb_strlen($listname) && 0 != mb_strlen($listid) && 0 != mb_strlen($email) && 0 != mb_strlen($pwd)) {
                    if (maillist_unsubscribe($xoopsUser, $xoopsDB, $_SERVER['HTTP_HOST'], $listname, $listid, urldecode($email), $pwd)) {
                        $subscribe_result .= _XF_MY_UNSUB_SUCCESS . "<br>\n";
                    } else {
                        $subscribe_result .= _XF_MY_UNSUB_FAIL . "<br>\n";
                    }
                } else {
                    $subscribe_result .= _XF_MY_NOUNSUB_NODATA . "<br>\n";
                }
            }
        }
    }
}

/***** SITE MAILING LISTS *****/
$xoopsTpl->assign('list_title', _XF_MY_SITELISTS);
$sql = 'SELECT list_name,list_id,allow_ru,allow_pa,allow_ca FROM ' . $xoopsDB->prefix('xf_maillist_sitelists') . " WHERE allow_ru = '1'";
if ($is_pa) {
    $sql .= " OR allow_pa = '1'";
}
if ($is_ca) {
    $sql .= " OR allow_ca = '1'";
}
$result = $xoopsDB->query($sql);
$rows = $xoopsDB->getRowsNum($result);
$avail_lists = [];
$total_avail_lists = 0;
for ($i = 0; $i < $rows; $i++) {
    $list_name = unofficial_getDBResult($result, $i, 'list_name');

    $avail_lists[$list_name] = unofficial_getDBResult($result, $i, 'list_id');

    $total_avail_lists++;
}

$sql = 'SELECT lists.list_name FROM ' . $xoopsDB->prefix('xf_maillist_sitelists') . ' lists, ' . $xoopsDB->prefix('xf_maillist_site_subscriptions') . ' subs ' . "WHERE subs.uid='" . $xoopsUser->getVar('uid') . "' AND lists.list_id=subs.list_id";
$result = $xoopsDB->query($sql);
$rows = $xoopsDB->getRowsNum($result);

$content = '<table>';
if (0 != mb_strlen($subscribe_result)) {
    $content .= "<tr><td colspan='2'><b>" . $subscribe_result . '</b></td></tr>';
}

$content .= "<form name='list_sub_form' method='POST' action='" . $_SERVER['PHP_SELF'] . "'>";
$content .= "<tr><td colspan='2'><input type='hidden' name='email' value='" . urlencode($xoopsUser->getVar('email')) . "'>";
if (!$result || $rows < 1) {
    $content .= _XF_MY_NOSUBSCRIPTIONS . '<br><br></td></tr>';
} else {
    $content .= _XF_MY_SUBSCRIPTIONS_HDR . "</td></tr><tr><td colspan='2'>";

    $content .= "<table border='0' width='100%' cellpadding='2' cellspacing='2'>";

    //$class = "";

    for ($i = 0; $i < $rows; $i++) {
        //$i % 2 ? $class = "bg2" : $class = "bg3";

        $list_name = unofficial_getDBResult($result, $i, 'list_name');

        $trans_list_name = strtr($list_name, '-', '_');

        $content .= "<input type='hidden' name='" . $trans_list_name . "_listid' value='" . $avail_lists[$list_name] . "'>";

        $content .= "<tr><td width='25%' class='centercolumn'>&nbsp;" . $list_name . '</td>';

        $content .= "<td align='center'><input type='password' name='" . $trans_list_name . "_pwd'></td>";

        $avail_lists[$list_name] = -1;

        $total_avail_lists--;

        $list_name = $trans_list_name;

        $content .= "<td align='center' width='20%'><input type='checkbox' name='"
                      . $list_name
                      . "_unsubscribe' onClick=\"if(document.list_sub_form."
                      . $list_name
                      . '_unsubscribe.checked&&(document.list_sub_form.'
                      . $list_name
                      . '_pwd.value==null||document.list_sub_form.'
                      . $list_name
                      . "_pwd.value=='')){document.list_sub_form."
                      . $list_name
                      . "_unsubscribe.checked=false;alert('"
                      . _XF_MY_PASSWD_REQD
                      . "');document.list_sub_form."
                      . $list_name
                      . '_pwd.focus();}">&nbsp;'
                      . _XF_MY_UNSUBSCRIBE
                      . '</td></tr>';
    }

    $content .= "<tr height='10'><td width='100%' colspan='3'></tr>";

    $content .= '</table></td></tr>';
}
if ($total_avail_lists) {
    $content .= "<tr><td colspan='2'>" . _XF_MY_AVAILABLE_SUBS . "</td></tr><tr><td colspan='2'>";

    $content .= "<table border='0' width='100%' cellpadding='2' cellspacing='2'>";

    $i = 0;

    foreach ($avail_lists as $list_name => $list_id) {
        if (-1 != $list_id) {
            $trans_list_name = strtr($list_name, '-', '_');

            $i++ % 2 ? $class = 'bg2' : $class = 'bg3';

            $content .= "<input type='hidden' name='" . $trans_list_name . "_list' value='" . $list_name . "'>";

            $content .= "<input type='hidden' name='" . $trans_list_name . "_listid' value='" . (string)$list_id . "'>";

            $content .= "<tr><td width='25%' class='centercolumn'>&nbsp;" . $list_name . '</td>';

            $content .= "<td align='center'><input type='password' name='" . $trans_list_name . "_pwd'></td>";

            $content .= "<td align='center'><input type='password' name='" . $trans_list_name . "_confpwd'></td>";

            $list_name = $trans_list_name;

            $content .= "<td align='center' width='20%'><input type='checkbox' name='"
                          . $list_name
                          . "_subscribe' onClick=\"if(document.list_sub_form."
                          . $list_name
                          . '_subscribe.checked&&(document.list_sub_form.'
                          . $list_name
                          . '_pwd.value==null||document.list_sub_form.'
                          . $list_name
                          . "_pwd.value=='')){document.list_sub_form."
                          . $list_name
                          . "_subscribe.checked=false;alert('"
                          . _XF_MY_PASSWD_REQD
                          . "');document.list_sub_form."
                          . $list_name
                          . '_pwd.focus();}else if(document.list_sub_form.'
                          . $list_name
                          . '_subscribe.checked&&document.list_sub_form.'
                          . $list_name
                          . '_pwd.value!=document.list_sub_form.'
                          . $list_name
                          . '_confpwd.value){document.list_sub_form.'
                          . $list_name
                          . "_subscribe.checked=false;alert('"
                          . _XF_MY_PASSWD_NOMATCH
                          . "');document.list_sub_form."
                          . $list_name
                          . '_pwd.focus();}">&nbsp;'
                          . _XF_MY_SUBSCRIBE
                          . '</td></tr>';
        }
    }

    //$i % 2 ? $class = "bg2" : $class = "bg3";

    $content .= "<tr height='10'><td width='100%' colspan='4'></tr>";

    $content .= '</table></td></tr>';
}

$content .= "<tr><td colspan='2'>";
$content .= "<table border='0' width='100%' cellpadding='2' cellspacing='2'>";
$content .= "<tr><td width='100%' align='center'><input type='submit' name='list_sub_form_submit' value='" . _XF_G_SUBMIT . "'></td></tr>";
$content .= '</table></td></tr>';

$content .= '</form></table>';

$xoopsTpl->assign('list_content', $content);

$xoopsTpl->assign('priority_colors', get_priority_colors_key());

include '../../footer.php';
