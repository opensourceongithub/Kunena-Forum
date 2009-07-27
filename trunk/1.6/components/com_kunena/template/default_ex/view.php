<?php
/**
* @version $Id: view.php 377 2009-02-12 08:52:32Z mahagr $
* Kunena Component
* @package Kunena
*
* @Copyright (C) 2008 - 2009 Kunena Team All rights reserved
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
* @link http://www.kunena.com
*
* Based on FireBoard Component
* @Copyright (C) 2006 - 2007 Best Of Joomla All rights reserved
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
* @link http://www.bestofjoomla.com
*
* Based on Joomlaboard Component
* @copyright (C) 2000 - 2004 TSMF / Jan de Graaff / All Rights Reserved
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
* @author TSMF & Jan de Graaff
**/

// Dont allow direct linking
defined( '_JEXEC' ) or die('Restricted access');

$app =& JFactory::getApplication();
$kunenaConfig =& CKunenaConfig::getInstance();
$kunenaSession =& CKunenaSession::getInstance();
$kunenaProfile =& CKunenaProfile::getInstance();

function KunenaViewPagination($catid, $threadid, $page, $totalpages, $maxpages) {
    $kunenaConfig =& CKunenaConfig::getInstance();

    $startpage = ($page - floor($maxpages/2) < 1) ? 1 : $page - floor($maxpages/2);
    $endpage = $startpage + $maxpages;
    if ($endpage > $totalpages) {
	$startpage = ($totalpages-$maxpages) < 1 ? 1 : $totalpages-$maxpages;
	$endpage = $totalpages;
    }

    $output = '<span class="kunena_pagination">'._PAGE;
    if ($startpage > 1)
    {
	if ($endpage < $totalpages) $endpage--;
	$output .= CKunenaLink::GetThreadPageLink($kunenaConfig, 'view', $catid, $threadid, 1, $kunenaConfig->messages_per_page, 1, '', $rel='follow');
	if ($startpage > 2)
        {
	    $output .= "...";
	}
    }

    for ($i = $startpage; $i <= $endpage && $i <= $totalpages; $i++)
    {
        if ($page == $i) {
            $output .= "<strong>$i</strong>";
        }
        else {
	    $output .= CKunenaLink::GetThreadPageLink($kunenaConfig, 'view', $catid, $threadid, $i, $kunenaConfig->messages_per_page, $i, '', $rel='follow');
        }
    }

    if ($endpage < $totalpages)
    {
	if ($endpage < $totalpages-1)
        {
	    $output .= "...";
	}

	$output .= CKunenaLink::GetThreadPageLink($kunenaConfig, 'view', $catid, $threadid, $totalpages, $kunenaConfig->messages_per_page, $totalpages, '', $rel='follow');
    }

    $output .= '</span>';
    return $output;
}

global $is_Moderator;
$kunena_acl = &JFactory::getACL();
//securing form elements
$catid = (int)$catid;
$id = (int)$id;

$smileyList = smile::getEmoticons(0);

//ob_start();
$showedEdit = 0;
require_once (KUNENA_PATH_LIB .DS. 'kunena.authentication.php');
require_once (KUNENA_PATH_LIB .DS. 'kunena.statsbar.php');

//get the allowed forums and turn it into an array
$allow_forum = ($kunenaSession->allowed <> '')?explode(',', $kunenaSession->allowed):array();

$forumLocked = 0;
$topicLocked = 0;

$kunena_db->setQuery("SELECT * FROM #__kunena_messages AS a WHERE a.id='{$id}' AND a.hold='0'");
$this_message = $kunena_db->loadObject();
check_dberror('Unable to load current message.');

if ((in_array($catid, $allow_forum)) || (isset($this_message->catid) && in_array($this_message->catid, $allow_forum)))
{
    $view = $view == "" ? $settings[current_view] : $view;
    setcookie("kunenaoard_settings[current_view]", $view, time() + 31536000, '/');

    $topicLocked = $this_message->locked;
    $topicSticky = $this_message->ordering;

    if (count($this_message) < 1) {
        echo '<p align="center">' . _MODERATION_INVALID_ID . '</p>';
    }
    else
    {
        $thread = $this_message->parent == 0 ? $this_message->id : $this_message->thread;

        // Test if this is a valid SEO URL if not we should redirect using a 301 - permanent redirect
        if ($thread != $this_message->id || $catid != $this_message->catid)
        {
        	// Invalid SEO URL detected!
        	// Create permanent re-direct and quit
        	// This query to calculate the page this reply is sitting on within this thread
        	$query = "SELECT COUNT(*) FROM #__kunena_messages AS a WHERE a.thread='{$thread}' AND hold='0' AND a.id<='{$id}'";
        	$kunena_db->setQuery($query);
        	$replyCount = $kunena_db->loadResult();
        		check_dberror('Unable to calculate location of current message.');

        	$replyPage = $replyCount > $kunenaConfig->messages_per_page ? ceil($replyCount / $kunenaConfig->messages_per_page) : 1;

        	header("HTTP/1.1 301 Moved Permanently");
        	header("Location: " . htmlspecialchars_decode(CKunenaLink::GetThreadPageURL($kunenaConfig, 'view', $this_message->catid, $thread, $replyPage, $kunenaConfig->messages_per_page, $this_message->id)));

        	$app->close();
        }

        if ($kunena_my->id)
        {
            //mark this topic as read
            $kunena_db->setQuery("SELECT readtopics FROM #__kunena_sessions WHERE userid='{$kunena_my->id}'");
            $readTopics = $kunena_db->loadResult();

            if ($readTopics == "")
            {
                $readTopics = $thread;
            }
            else
            {
                //get all readTopics in an array
                $_read_topics = @explode(',', $readTopics);

                if (!@in_array($thread, $_read_topics)) {
                    $readTopics .= "," . $thread;
                }
            }

            $kunena_db->setQuery("UPDATE #__kunena_sessions SET readtopics='{$readTopics}' WHERE userid='{$kunena_my->id}'");
            $kunena_db->query();
        }

        //update the hits counter for this topic & exclude the owner
        if ($this_message->userid != $kunena_my->id) {
            $kunena_db->setQuery("UPDATE #__kunena_messages SET hits=hits+1 WHERE id='{$thread}' AND parent='0'");
            $kunena_db->query();
        }

		$query = "SELECT COUNT(*) FROM #__kunena_messages AS a WHERE a.thread='{$thread}' AND hold='0'";
		$kunena_db->setQuery($query);
		$total = $kunena_db->loadResult();
		check_dberror('Unable to calculate message count.');

        //prepare paging
        $limit = JRequest::getInt('limit', $kunenaConfig->messages_per_page);
		$limitstart = JRequest::getInt('limitstart', 0);
        $ordering = ($kunenaConfig->default_sort == 'desc' ? 'desc' : 'asc'); // Just to make sure only valid options make it
		$maxpages = 9 - 2; // odd number here (show - 2)
		$totalpages = ceil($total / $limit);
		$page = floor($limitstart / $limit)+1;
		$firstpage = 1;
		if ($ordering == 'desc') $firstpage = $totalpages;

		$replylimit = $page == $firstpage ? $limit-1 : $limit; // If page contains first message, load $limit-1 messages
		$replystart = $limitstart && $ordering == 'asc' ? $limitstart-1 : $limitstart; // If not first page and order=asc, start on $limitstart-1
		// Get replies of current thread
        $query = "SELECT * FROM #__kunena_messages AS a "
        	."WHERE a.thread='{$thread}' AND a.id!='{$id}' AND a.hold='0' AND a.catid='{$catid}' ORDER BY id {$ordering}";
		$kunena_db->setQuery($query, $replystart, $replylimit);
		$replies = $kunena_db->loadObjectList();
		check_dberror('Unable to load replies');

		$flat_messages = array();
        if ($page == 1 && $ordering == 'asc') $flat_messages[] = $this_message; // ASC: first message is the first one
        foreach ($replies as $message) $flat_messages[] = $message;
        if ($page == $totalpages && $ordering == 'desc') $flat_messages[] = $this_message; // DESC: first message is the last one
        unset($replies);

	    $pagination = KunenaViewPagination($catid, $thread, $page, $totalpages, $maxpages);

        //Get the category name for breadcrumb
        $kunena_db->setQuery("SELECT * FROM #__kunena_categories WHERE id='{$catid}'");
        $objCatInfo = $kunena_db->loadObject();
        //Get Parent's cat.name for breadcrumb
        $kunena_db->setQuery("SELECT id, name FROM #__kunena_categories WHERE id='{$objCatInfo->parent}'");
        $objCatParentInfo = $kunena_db->loadObject();

        $forumLocked = $objCatInfo->locked;

		//meta description and keywords
		$metaKeys=kunena_htmlspecialchars(stripslashes("{$this_message->subject}, {$objCatParentInfo->name}, {$kunenaConfig->board_title}, " ._GEN_FORUM. ', ' .$app->getCfg('sitename')));
		$metaDesc=kunena_htmlspecialchars(stripslashes("{$this_message->subject} ({$page}/{$totalpages}) - {$objCatParentInfo->name} - {$objCatInfo->name} - {$kunenaConfig->board_title} " ._GEN_FORUM));

	    $document =& JFactory::getDocument();
	    $cur = $document->get( 'description' );
	    $metaDesc = $cur .'. ' . $metaDesc;
	    $document->setMetadata( 'keywords', $metaKeys );
	    $document->setDescription($metaDesc);

        //Perform subscriptions check only once
        $kunena_cansubscribe = 0;
        if ($kunenaConfig->allowsubscriptions && ("" != $kunena_my->id || 0 != $kunena_my->id))
        {
            $kunena_db->setQuery("SELECT thread FROM #__kunena_subscriptions WHERE userid='{$kunena_my->id}' AND thread='{$thread}'");
            $kunena_subscribed = $kunena_db->loadResult();

            if ($kunena_subscribed == "") {
                $kunena_cansubscribe = 1;
            }
        }
        //Perform favorites check only once
        $kunena_canfavorite = 0;
        if ($kunenaConfig->allowfavorites && ("" != $kunena_my->id || 0 != $kunena_my->id))
        {
            $kunena_db->setQuery("SELECT thread FROM #__kunena_favorites WHERE userid='{$kunena_my->id}' AND thread='{$thread}'");
            $kunena_favorited = $kunena_db->loadResult();

            if ($kunena_favorited == "") {
                $kunena_canfavorite = 1;
            }
        }

        //data ready display now

        if ($is_Moderator || (($forumLocked == 0 && $topicLocked == 0) && ($kunena_my->id > 0 || $kunenaConfig->pubwrite)))
        {
            //this user is allowed to reply to this topic
            $thread_reply = CKunenaLink::GetTopicPostReplyLink('reply', $catid, $thread, isset($kunenaIcons['topicreply']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['topicreply'] . '" alt="' . _GEN_POST_REPLY . '" title="' . _GEN_POST_REPLY . '" border="0" />' : _GEN_POST_REPLY);
        }

        if ($kunena_cansubscribe == 1)
        {
            // this user is allowed to subscribe - check performed further up to eliminate duplicate checks
            // for top and bottom navigation
            $thread_subscribe = CKunenaLink::GetTopicPostLink('subscribe', $catid, $id, isset($kunenaIcons['subscribe']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['subscribe'] . '" alt="' . _VIEW_SUBSCRIBETXT . '" title="' . _VIEW_SUBSCRIBETXT . '" border="0" />' : _VIEW_SUBSCRIBETXT);
        }

        //START: FAVORITES
        if ($kunena_my->id != 0 && $kunenaConfig->allowsubscriptions && $kunena_cansubscribe == 0)
        {
            // this user is allowed to unsubscribe
            $thread_subscribe = CKunenaLink::GetTopicPostLink('unsubscribe', $catid, $id, isset($kunenaIcons['unsubscribe']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['unsubscribe'] . '" alt="' . _VIEW_UNSUBSCRIBETXT . '" title="' . _VIEW_UNSUBSCRIBETXT . '" border="0" />' : _VIEW_UNSUBSCRIBETXT);
        }

        if ($kunena_canfavorite == 1)
        {
            // this user is allowed to add a favorite - check performed further up to eliminate duplicate checks
            // for top and bottom navigation
            $thread_favorite = CKunenaLink::GetTopicPostLink('favorite', $catid, $id, isset($kunenaIcons['favorite']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['favorite'] . '" alt="' . _VIEW_FAVORITETXT . '" title="' . _VIEW_FAVORITETXT . '" border="0" />' : _VIEW_FAVORITETXT);
        }

        if ($kunena_my->id != 0 && $kunenaConfig->allowfavorites && $kunena_canfavorite == 0)
        {
            // this user is allowed to unfavorite
            $thread_favorite = CKunenaLink::GetTopicPostLink('unfavorite', $catid, $id, isset($kunenaIcons['unfavorite']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['unfavorite'] . '" alt="' . _VIEW_UNFAVORITETXT . '" title="' . _VIEW_UNFAVORITETXT . '" border="0" />' : _VIEW_UNFAVORITETXT);
        }
        // FINISH: FAVORITES

        if ($is_Moderator || ($forumLocked == 0 && ($kunena_my->id > 0 || $kunenaConfig->pubwrite)))
        {
            //this user is allowed to post a new topic
            $thread_new = CKunenaLink::GetPostNewTopicLink($catid, isset($kunenaIcons['new_topic']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['new_topic'] . '" alt="' . _GEN_POST_NEW_TOPIC . '" title="' . _GEN_POST_NEW_TOPIC . '" border="0" />' : _GEN_POST_NEW_TOPIC);
        }

        if ($is_Moderator)
        {
            // offer the moderator always the move link to relocate a topic to another forum
            // and the (un)sticky bit links
            // and the (un)lock links
            $thread_move = CKunenaLink::GetTopicPostLink('move', $catid, $id, isset($kunenaIcons['move']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['move'] . '" alt="Move" border="0" title="' . _VIEW_MOVE . '" />':_GEN_MOVE);

            if ($topicSticky == 0)
            {
                $thread_sticky = CKunenaLink::GetTopicPostLink('sticky', $catid, $id, isset($kunenaIcons['sticky']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['sticky'] . '" alt="Sticky" border="0" title="' . _VIEW_STICKY . '" />':_GEN_STICKY);
            }
            else
            {
                $thread_sticky = CKunenaLink::GetTopicPostLink('unsticky', $catid, $id, isset($kunenaIcons['unsticky']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['unsticky'] . '" alt="Unsticky" border="0" title="' . _VIEW_UNSTICKY . '" />':_GEN_UNSTICKY);
            }

            if ($topicLocked == 0)
            {
                $thread_lock = CKunenaLink::GetTopicPostLink('lock', $catid, $id, isset($kunenaIcons['lock']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['lock'] . '" alt="Lock" border="0" title="' . _VIEW_LOCK . '" />':_GEN_LOCK);
            }
            else
            {
                $thread_lock = CKunenaLink::GetTopicPostLink('unlock', $catid, $id, isset($kunenaIcons['unlock']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['unlock'] . '" alt="Unlock" border="0" title="' . _VIEW_UNLOCK . '" />':_GEN_UNLOCK);
            }
            $thread_delete = CKunenaLink::GetTopicPostLink('delete', $catid, $id, isset($kunenaIcons['delete']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['delete'] . '" alt="Delete" border="0" title="' . _VIEW_DELETE . '" />':_GEN_DELETE);
            $thread_merge = CKunenaLink::GetTopicPostLink('merge', $catid, $id, isset($kunenaIcons['merge']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['merge'] . '" alt="Merge" border="0" title="' . _VIEW_MERGE . '" />':_GEN_MERGE);
        }
?>

        <script type = "text/javascript">
        jQuery(function()
        {
            jQuery(".kunena_qr_fire").click(function()
            {
                jQuery("#sc" + (jQuery(this).attr("id").split("__")[1])).toggle();
            });
            jQuery(".kunena_qm_cncl_btn").click(function()
            {
                jQuery("#sc" + (jQuery(this).attr("id").split("__")[1])).toggle();
            });

        });
        </script>

        <div>
            <?php
            if (file_exists(KUNENA_ABSTMPLTPATH . '/kunena_pathway.php')) {
                require_once (KUNENA_ABSTMPLTPATH . '/kunena_pathway.php');
            }
            else {
                require_once (KUNENA_PATH_TEMPLATE_DEFAULT .DS. 'kunena_pathway.php');
            }
            ?>
        </div>
        <?php if($objCatInfo->headerdesc) { ?>
		<table class="kunena_forum-headerdesc" border="0" cellpadding="0" cellspacing="0" width="100%">
			<tr>
				<td>
					<?php
					$headerdesc = stripslashes(smile::smileReplace($objCatInfo->headerdesc, 0, $kunenaConfig->disemoticons, $smileyList));
			        $headerdesc = nl2br($headerdesc);
			        //wordwrap:
			        $headerdesc = smile::htmlwrap($headerdesc, $kunenaConfig->wrap);
					echo $headerdesc;
					?>
				</td>
			</tr>
		</table>
        <?php } ?>

        <!-- B: List Actions -->

        <table class="kunena_list_actions" border = "0" cellspacing = "0" cellpadding = "0" width="100%">
            <tr>
                <td class = "kunena_list_actions_goto">
                    <?php
                    //go to bottom
                    echo '<a name="forumtop" /> ';
                    echo CKunenaLink::GetSamePageAnkerLink('forumbottom', isset($kunenaIcons['bottomarrow']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['bottomarrow'] . '" border="0" alt="' . _GEN_GOTOBOTTOM . '" title="' . _GEN_GOTOBOTTOM . '"/>' : _GEN_GOTOBOTTOM);

	echo '</td>';
	if ($is_Moderator || isset($thread_reply) || isset($thread_subscribe) || isset($thread_favorite))
	{
	    echo '<td class="kunena_list_actions_forum">';
	    echo '<div class="kunena_message_buttons_row" style="text-align: center;">';
	    if (isset($thread_reply)) echo $thread_reply;
	    if (isset($thread_subscribe)) echo ' '.$thread_subscribe;
	    if (isset($thread_favorite)) echo ' '.$thread_favorite;
	    echo '</div>';
            if ($is_Moderator)
            {
		echo '<div class="kunena_message_buttons_row" style="text-align: center;">';
		echo $thread_delete;
		echo ' '.$thread_move;
		echo ' '.$thread_sticky;
		echo ' '.$thread_lock;
		echo '</div>';
	    }
            echo '</td>';
	}
	echo '<td class="kunena_list_actions_forum" width="100%">';
        if (isset($thread_new))
        {
	    echo '<div class="kunena_message_buttons_row" style="text-align: left;">';
	    echo $thread_new;
	    echo '</div>';
        }
        if (isset($thread_merge))
        {
	    echo '<div class="kunena_message_buttons_row" style="text-align: left;">';
	    echo $thread_merge;
	    echo '</div>';
	}
	echo '</td>';

	//pagination 1
	echo '<td class="kunena_list_pages_all" nowrap="nowrap">';
	echo $pagination;
	echo '</td>';
	?>
            </tr>
        </table>

        <!-- F: List Actions -->

        <!-- <table border = "0" cellspacing = "0" cellpadding = "0" width = "100%" align = "center"> -->

            <table class = "kunena_blocktable<?php echo $objCatInfo->class_sfx; ?>"  id="kunena_views" cellpadding = "0" cellspacing = "0" border = "0" width = "100%">
                <thead>
                    <tr>
                        <th align="left">
                             <div class = "kunena_title_cover  kunenam">
                                <span class = "kunena_title kunenal"><b><?php echo _KUNENA_TOPIC; ?></b> <?php echo $jr_topic_title; ?></span>
                            </div>
                            <!-- B: FORUM TOOLS -->

                            <?php

                            //(JJ) BEGIN: RECENT POSTS
                            if (file_exists(KUNENA_ABSTMPLTPATH . '/plugin/forumtools/forumtools.php')) {
                                include (KUNENA_ABSTMPLTPATH . '/plugin/forumtools/forumtools.php');
                            }
                            else {
                                include (KUNENA_PATH_TEMPLATE_DEFAULT .DS. 'plugin/forumtools/forumtools.php');
                            }

                            //(JJ) FINISH: RECENT POSTS

                            ?>
			    <!-- F: FORUM TOOLS -->
        	            <!-- Begin: Total Favorite -->
	                    <?php
        	            $kunena_db->setQuery("SELECT COUNT(*) FROM #__kunena_favorites WHERE thread='{$thread}'");
        	            $kunena_totalfavorited = $kunena_db->loadResult();

	                    echo '<div class="kunena_totalfavorite">';
			    if ($kunenaIcons['favoritestar']) {
			        if ($kunena_totalfavorited>=1) echo '<img src="'.KUNENA_URLICONSPATH . $kunenaIcons['favoritestar'].'" alt="*" border="0" title="' . _KUNENA_FAVORITE . '" />';
			        if ($kunena_totalfavorited>=3) echo '<img src="'.KUNENA_URLICONSPATH . $kunenaIcons['favoritestar'].'" alt="*" border="0" title="' . _KUNENA_FAVORITE . '" />';
			        if ($kunena_totalfavorited>=6) echo '<img src="'.KUNENA_URLICONSPATH . $kunenaIcons['favoritestar'].'" alt="*" border="0" title="' . _KUNENA_FAVORITE . '" />';
			        if ($kunena_totalfavorited>=10) echo '<img src="'.KUNENA_URLICONSPATH . $kunenaIcons['favoritestar'].'" alt="*" border="0" title="' . _KUNENA_FAVORITE . '" />';
			        if ($kunena_totalfavorited>=15) echo '<img src="'.KUNENA_URLICONSPATH . $kunenaIcons['favoritestar'].'" alt="*" border="0" title="' . _KUNENA_FAVORITE . '" />';
			    } else {
                                echo _KUNENA_TOTALFAVORITE;
                                echo $kunena_totalfavorited;
			    }
        	            echo '</div>';
        	            ?>
	                    <!-- Finish: Total Favorite -->
                        </th>
                    </tr>
                </thead>

                <tr>
                    <td>
                        <?php
                        $tabclass = array
                        (
                        "sectiontableentry1",
                        "sectiontableentry2"
                        );

                        $mmm = 0;
                        $k = 0;
                        // Set up a list of moderators for this category (limits amount of queries)
                        $kunena_db->setQuery("SELECT a.userid FROM #__kunena_users AS a LEFT JOIN #__kunena_moderation AS b ON b.userid=a.userid WHERE b.catid='{$catid}'");
                        $catModerators = $kunena_db->loadResultArray();


                        /**
                        * note: please check if this routine is fine. there is no need to see for all messages if they are locked or not, either the thread or cat can be locked anyway
                        */

                        //check if topic is locked
                        $_lockTopicID = $this_message->thread;
                        $topicLocked = $this_message->locked;

                        if ($_lockTopicID) // prev UNDEFINED $topicID!!
                        {
                            $lockedWhat = _TOPIC_NOT_ALLOWED; // UNUSED
                        }

                        else
                        { //topic not locked; check if forum is locked
                            $kunena_db->setQuery("SELECT locked FROM #__kunena_categories WHERE id='{$this_message->catid}'");
                            $topicLocked = $kunena_db->loadResult();
                            $lockedWhat = _FORUM_NOT_ALLOWED; // UNUSED
                        }
                        // END TOPIC LOCK

                        if (count($flat_messages) > 0)
                        {
                            foreach ($flat_messages as $fmessage)
                            {

                                $k = 1 - $k;
                                $mmm++;

                                if ($fmessage->parent == 0) {
                                    $kunena_thread = $fmessage->id;
                                }
                                else {
                                    $kunena_thread = $fmessage->thread;
                                }

                                //filter out clear html
                                $fmessage->name = kunena_htmlspecialchars($fmessage->name);
                                $fmessage->email = kunena_htmlspecialchars($fmessage->email);
                                $fmessage->subject = kunena_htmlspecialchars($fmessage->subject);

                                //Get userinfo needed later on, this limits the amount of queries
                                unset($userinfo);
                                $kunena_db->setQuery("SELECT  a.*, b.id, b.name, b.username, b.gid FROM #__kunena_users AS a LEFT JOIN #__users AS b ON b.id=a.userid WHERE a.userid='{$fmessage->userid}'");
                                $userinfo = $kunena_db->loadObject();
				if ($userinfo == NULL) {
					$userinfo = new stdClass();
					$userinfo->userid = 0;
					$userinfo->name = '';
					$userinfo->username = '';
					$userinfo->avatar = '';
					$userinfo->gid = 0;
					$userinfo->rank = 0;
					$userinfo->posts = 0;
					$userinfo->karma = 0;
					$userinfo->gender = _KUNENA_NOGENDER;
					$userinfo->personalText = '';
					$userinfo->ICQ = '';
					$userinfo->location = '';
					$userinfo->birthdate = '';
					$userinfo->AIM = '';
					$userinfo->MSN = '';
					$userinfo->YIM = '';
					$userinfo->SKYPE = '';
					$userinfo->GTALK = '';
					$userinfo->websiteurl = '';
					$userinfo->signature = '';
				}

				$triggerParams = array( 'userid'=> $fmessage->userid,
					'userinfo'=> &$userinfo );
				$kunenaProfile->trigger( 'profileIntegration', $triggerParams );

                                //get the username:
                                if ($kunenaConfig->username) {
                                    $kunena_queryName = "username";
                                }
                                else {
                                    $kunena_queryName = "name";
                                }

                                $kunena_username = $userinfo->$kunena_queryName;

                                if ($kunena_username == "" || $kunenaConfig->changename) {
                                    $kunena_username = stripslashes($fmessage->name);
                                }
                                $kunena_username = kunena_htmlspecialchars($kunena_username);

                                $msg_id = $fmessage->id;
                                $lists["userid"] = $userinfo->userid;
                                $msg_username = $fmessage->email != "" && $my_id > 0 && $kunenaConfig->showemail ? CKunenaLink::GetEmailLink(kunena_htmlspecialchars(stripslashes($fmessage->email)), $kunena_username) : $kunena_username;

                                if ($kunenaConfig->allowavatar)
                                {
                                    $Avatarname = $userinfo->username;
                                   	$msg_avatar = '<span class="kunena_avatar">'.$kunenaProfile->showAvatar($userinfo->userid, '', false).'</span>';
                                }

                                if ($kunenaConfig->showuserstats)
                                {
								    $kunena_acl =& JFactory::getACL();
                                    //user type determination
                                    $ugid = $userinfo->gid;
                                    $uIsMod = 0;
                                    $uIsAdm = 0;
                                    $uIsMod = in_array($userinfo->userid, $catModerators);

                                    if ($ugid > 0) { //only get the groupname from the ACL if we're sure there is one
                                        $agrp = strtolower($kunena_acl->get_group_name($ugid, 'ARO'));
                                    }

                                    if ($ugid == 0) {
                                        $msg_usertype = _VIEW_VISITOR;
                                    }
                                    else
                                    {
                                        if (strtolower($agrp) == "administrator" || strtolower($agrp) == "superadministrator" || strtolower($agrp) == "super administrator")
                                        {
                                            $msg_usertype = _VIEW_ADMIN;
                                            $uIsAdm = 1;
                                        }
                                        elseif ($uIsMod) {
                                            $msg_usertype = _VIEW_MODERATOR;
                                        }
                                        else {
                                            $msg_usertype = _VIEW_USER;
                                        }
                                    }

                                    //done usertype determination, phew...
                                    //# of post for this user and ranking
                                    if ($userinfo->userid)
                                    {
                                        $numPosts = (int)$userinfo->posts;

                                        //ranking
                                        $rText = ''; $showSpRank = false;
                                        if ($kunenaConfig->showranking)
                                        {

                                            if ($showSpRank = $userinfo->rank != '0')
                                            {
                                                //special rank
                                                $kunena_db->setQuery("SELECT * FROM #__kunena_ranks WHERE rank_id='{$userinfo->rank}'");
                                            } else {
                                                //post count rank
                                                $kunena_db->setQuery("SELECT * FROM #__kunena_ranks WHERE ((rank_min <= '{$numPosts}') AND (rank_special = '0')) ORDER BY rank_min DESC", 0, 1);
                                            }
                                            $rank = $kunena_db->loadObject();
                                            $rText = $rank->rank_title;
                                            $rImg = KUNENA_URLRANKSPATH . $rank->rank_image;
                                        }

                                        if ($uIsMod and !$showSpRank)
                                        {
                                            $rText = _RANK_MODERATOR;
                                            $rImg = KUNENA_URLRANKSPATH . 'rankmod.gif';
                                        }

                                        if ($uIsAdm and !$showSpRank)
                                        {
                                            $rText = _RANK_ADMINISTRATOR;
                                            $rImg = KUNENA_URLRANKSPATH . 'rankadmin.gif';
                                        }

                                        if ($kunenaConfig->rankimages && isset($rImg)) {
                                            $msg_userrankimg = '<img src="' . $rImg . '" alt="" />';
                                        }

                                        $msg_userrank = $rText;





                                        $useGraph = 0; //initialization

                                        if (!$kunenaConfig->poststats)
                                        {
                                            $msg_posts = '<div class="viewcover">' .
                                              "<strong>" . _POSTS . " $numPosts" . "</strong>" .
                                              "</div>";
                                        }
                                        else
                                        {
                                            $myGraph = new phpGraph;
                                            //$myGraph->SetGraphTitle(_POSTS);
                                            $myGraph->AddValue(_POSTS, $numPosts);
                                            $myGraph->SetRowSortMode(0);
                                            $myGraph->SetBarImg(KUNENA_URLGRAPHPATH . "col" . $kunenaConfig->statscolor . "m.png");
                                            $myGraph->SetBarImg2(KUNENA_URLEMOTIONSPATH . "graph.gif");
                                            $myGraph->SetMaxVal($maxPosts);
                                            $myGraph->SetShowCountsMode(2);
                                            $myGraph->SetBarWidth(4); //height of the bar
                                            $myGraph->SetBorderColor("#333333");
                                            $myGraph->SetBarBorderWidth(0);
                                            $myGraph->SetGraphWidth(64); //should match column width in the <TD> above -5 pixels
                                            //$myGraph->BarGraphHoriz();
                                            $useGraph = 1;
                                        }
                                    }
                                }

                                //karma points and buttons
                                if ($kunenaConfig->showkarma && $userinfo->userid != '0')
                                {
                                    $karmaPoints = $userinfo->karma;
                                    $karmaPoints = (int)$karmaPoints;
                                    $msg_karma = "<strong>" . _KARMA . ":</strong> $karmaPoints";

                                    if ($kunena_my->id != '0' && $kunena_my->id != $userinfo->userid)
                                    {
                                        $msg_karmaminus = CKunenaLink::GetKarmaLink('decrease', $catid, $fmessage->id, $userinfo->userid, '<img src="'.(isset($kunenaIcons['karmaminus'])?(KUNENA_URLICONSPATH . $kunenaIcons['karmaminus']):(KUNENA_URLEMOTIONSPATH . "karmaminus.gif")).'" alt="Karma-" border="0" title="' . _KARMA_SMITE . '" align="middle" />' );
                                        $msg_karmaplus  = CKunenaLink::GetKarmaLink('increase', $catid, $fmessage->id, $userinfo->userid, '<img src="'.(isset($kunenaIcons['karmaplus'])?(KUNENA_URLICONSPATH . $kunenaIcons['karmaplus']):(KUNENA_URLEMOTIONSPATH . "karmaplus.gif")).'" alt="Karma+" border="0" title="' . _KARMA_APPLAUD . '" align="middle" />' );
                                    }
                                }

								/* PM integration */
								$msg_pms = CKunenaPMS::showPMIcon($userinfo);

                                // online - ofline status
                                if ($userinfo->userid > 0)
                                {
                                    $sql = "SELECT COUNT(userid) FROM #__session WHERE userid='{$userinfo->userid}'";
                                    $kunena_db->setQuery($sql);
                                    $isonline = $kunena_db->loadResult();

                                    if ($isonline && $userinfo->showOnline ==1 ) {
                                        $msg_online = isset($kunenaIcons['onlineicon']) ? '<img src="'
                                        . KUNENA_URLICONSPATH . $kunenaIcons['onlineicon'] . '" border="0" alt="' . _MODLIST_ONLINE . '" />' : '  <img src="' . KUNENA_URLEMOTIONSPATH . 'onlineicon.gif" border="0"  alt="' . _MODLIST_ONLINE . '" />';
                                    }
                                    else {
                                        $msg_online = isset($kunenaIcons['offlineicon']) ? '<img src="'
                                        . KUNENA_URLICONSPATH . $kunenaIcons['offlineicon'] . '" border="0" alt="' . _MODLIST_OFFLINE . '" />' : '  <img src="' . KUNENA_URLEMOTIONSPATH . 'offlineicon.gif" border="0"  alt="' . _MODLIST_OFFLINE . '" />';
                                    }
                                }

                                if ($userinfo->gid > 0)
                                {
                                    $msg_prflink = $kunenaProfile->getProfileURL($userinfo->userid);
                                	if ($kunenaIcons['userprofile']) {
                                        $msg_profileicon = KUNENA_URLICONSPATH . $kunenaIcons['userprofile'];
                                    }
                                    else {
                                        $msg_profileicon = KUNENA_URLICONSPATH . "profile.gif";
                                    }

                                    $msg_profile = '<a href="' . $msg_prflink . '"><img src="' . $msg_profileicon . '" alt="' . _VIEW_PROFILE . '" border="0" title="' . _VIEW_PROFILE . '" /></a>';
                                }

                                // Begin: Additional Info //
                                if ($userinfo->gender != '') {
                                    $gender = _KUNENA_NOGENDER;
                                    if ($userinfo->gender ==1)  {
                                        $gender = ''._KUNENA_MYPROFILE_MALE;
                                        $msg_gender = isset($kunenaIcons['msgmale']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgmale'] . '" border="0" alt="'._KUNENA_MYPROFILE_GENDER.': '.$gender.'" title="'._KUNENA_MYPROFILE_GENDER.': '.$gender.'" />' : ''._KUNENA_MYPROFILE_GENDER.': '.$gender.'';
                                    }

                                    if ($userinfo->gender ==2)  {
                                        $gender = ''._KUNENA_MYPROFILE_FEMALE;
                                        $msg_gender = isset($kunenaIcons['msgfemale']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgfemale'] . '" border="0" alt="'._KUNENA_MYPROFILE_GENDER.': '.$gender.'" title="'._KUNENA_MYPROFILE_GENDER.': '.$gender.'" />' : ''._KUNENA_MYPROFILE_GENDER.': '.$gender.'';
                                    }

                                }

                                if ($userinfo->personalText != '') {
                                    $msg_personal = kunena_htmlspecialchars(stripslashes($userinfo->personalText));
                                }

                                if ($userinfo->ICQ != '') {
                                    $msg_icq = '<a href="http://www.icq.com/people/cmd.php?uin='.kunena_htmlspecialchars(stripslashes($userinfo->ICQ)).'&action=message"><img src="http://status.icq.com/online.gif?icq='.kunena_htmlspecialchars(stripslashes($userinfo->ICQ)).'&img=5" title="ICQ#: '.kunena_htmlspecialchars(stripslashes($userinfo->ICQ)).'" alt="ICQ#: '.kunena_htmlspecialchars(stripslashes($userinfo->ICQ)).'" /></a>';
                                }
                                if ($userinfo->location != '') {
                                    $msg_location = isset($kunenaIcons['msglocation']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msglocation'] . '" border="0" alt="'._KUNENA_MYPROFILE_LOCATION.': '.kunena_htmlspecialchars(stripslashes($userinfo->location)).'" title="'._KUNENA_MYPROFILE_LOCATION.': '.kunena_htmlspecialchars(stripslashes($userinfo->location)).'" />' : ' '._KUNENA_MYPROFILE_LOCATION.': '.kunena_htmlspecialchars(stripslashes($userinfo->location)).'';
                                }
                                if ($userinfo->birthdate !='0001-01-01' AND $userinfo->birthdate !='0000-00-00' and $userinfo->birthdate !='') {
                                	$birthday = strftime(_KUNENA_DT_MONTHDAY_FMT, strtotime($userinfo->birthdate));
                                    $msg_birthdate = isset($kunenaIcons['msgbirthdate']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgbirthdate'] . '" border="0" alt="'._KUNENA_PROFILE_BIRTHDAY.': '.$birthday.'" title="'._KUNENA_PROFILE_BIRTHDAY.': '.$birthday.'" />' : ' '._KUNENA_PROFILE_BIRTHDAY.': '.$birthday.'';
                            	}

                                if ($userinfo->AIM != '') {
                                    $msg_aim = isset($kunenaIcons['msgaim']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgaim'] . '" border="0" alt="'.kunena_htmlspecialchars(stripslashes($userinfo->AIM)).'" title="AIM: '.kunena_htmlspecialchars(stripslashes($userinfo->AIM)).'" />' : 'AIM: '.kunena_htmlspecialchars(stripslashes($userinfo->AIM)).'';
                                }
                                if ($userinfo->MSN != '') {
                                    $msg_msn = isset($kunenaIcons['msgmsn']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgmsn'] . '" border="0" alt="'.kunena_htmlspecialchars(stripslashes($userinfo->MSN)).'" title="MSN: '.kunena_htmlspecialchars(stripslashes($userinfo->MSN)).'" />' : 'MSN: '.kunena_htmlspecialchars(stripslashes($userinfo->MSN)).'';
                                }
                                if ($userinfo->YIM != '') {
                                    $msg_yim = isset($kunenaIcons['msgyim']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgyim'] . '" border="0" alt="'.kunena_htmlspecialchars(stripslashes($userinfo->YIM)).'" title="YIM: '.kunena_htmlspecialchars(stripslashes($userinfo->YIM)).'" />' : ' YIM: '.kunena_htmlspecialchars(stripslashes($userinfo->YIM)).'';
                                }
                                if ($userinfo->SKYPE != '') {
                                    $msg_skype = isset($kunenaIcons['msgskype']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgskype'] . '" border="0" alt="'.kunena_htmlspecialchars(stripslashes($userinfo->SKYPE)).'" title="SKYPE: '.kunena_htmlspecialchars(stripslashes($userinfo->SKYPE)).'" />' : 'SKYPE: '.kunena_htmlspecialchars(stripslashes($userinfo->SKYPE)).'';
                                }
                                if ($userinfo->GTALK != '') {
                                    $msg_gtalk = isset($kunenaIcons['msggtalk']) ? '<img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msggtalk'] . '" border="0" alt="'.kunena_htmlspecialchars(stripslashes($userinfo->GTALK)).'" title="GTALK: '.kunena_htmlspecialchars(stripslashes($userinfo->GTALK)).'" />' : 'GTALK: '.kunena_htmlspecialchars(stripslashes($userinfo->GTALK)).'';
                                }
                                if ($userinfo->websiteurl != '') {
                                    $msg_website = isset($kunenaIcons['msgwebsite']) ? '<a href="http://'.kunena_htmlspecialchars(stripslashes($userinfo->websiteurl)).'" target="_blank"><img src="'. KUNENA_URLICONSPATH . $kunenaIcons['msgwebsite'] . '" border="0" alt="'.kunena_htmlspecialchars(stripslashes($userinfo->websitename)).'" title="'.kunena_htmlspecialchars(stripslashes($userinfo->websitename)).'" /></a>' : '<a href="http://'.kunena_htmlspecialchars(stripslashes($userinfo->websiteurl)).'" target="_blank">'.kunena_htmlspecialchars(stripslashes($userinfo->websitename)).'</a>';
                                }

                                // Finish: Additional Info //


                                //Show admins the IP address of the user:
                                if ($is_Moderator)
                                {
                                    $msg_ip = $fmessage->ip;
                                }

                                $kunena_subject_txt = $fmessage->subject;

                                $table = array_flip(get_html_translation_table(HTML_ENTITIES));

                                $kunena_subject_txt = strtr($kunena_subject_txt, $table);
                                $kunena_subject_txt = stripslashes($kunena_subject_txt);
                                $msg_subject = smile::kunenaHtmlSafe($kunena_subject_txt);

                                $msg_date = date(_DATETIME, $fmessage->time);
                                $kunena_message_txt = stripslashes($fmessage->message);

                                $kunena_message_txt = smile::smileReplace($kunena_message_txt, 0, $kunenaConfig->disemoticons, $smileyList);
                                $kunena_message_txt = nl2br($kunena_message_txt);
                                //$kunena_message_txt = str_replace("<P>&nbsp;</P><br />","",$kunena_message_txt);
                                //$kunena_message_txt = str_replace("</P><br />","</P>",$kunena_message_txt);
                                //$kunena_message_txt = str_replace("<P><br />","<P>",$kunena_message_txt);

                                // Code tag: restore TABS as we had to 'hide' them from the rest of the logic
                                $kunena_message_txt = str_replace("__FBTAB__", "&#009;", $kunena_message_txt);

                                $msg_text = CKunenaTools::prepareContent($kunena_message_txt);

                                $signature = $userinfo->signature;
                                if ($signature)
                                {
                                    $signature = stripslashes(smile::smileReplace($signature, 0, $kunenaConfig->disemoticons, $smileyList));
                                    $signature = nl2br($signature);
                                    //wordwrap:
                                    $signature = smile::htmlwrap($signature, $kunenaConfig->wrap);
                                    //restore the \n (were replaced with _CTRL_) occurences inside code tags, but only after we have striplslashes; otherwise they will be stripped again
                                    //$signature = str_replace("_CRLF_", "\\n", stripslashes($signature));
                                    $msg_signature = $signature;
                                }

                                if ($is_Moderator || (($forumLocked == 0 && $topicLocked == 0) && ($kunena_my->id > 0 || $kunenaConfig->pubwrite)))
                                {
                                    //user is allowed to reply/quote
                                    $msg_reply = CKunenaLink::GetTopicPostReplyLink('reply', $catid, $fmessage->id , isset($kunenaIcons['reply']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['reply'] . '" alt="Reply" border="0" title="' . _VIEW_REPLY . '" />':_GEN_REPLY);
                                    $msg_quote = CKunenaLink::GetTopicPostReplyLink('quote', $catid, $fmessage->id , isset($kunenaIcons['quote']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['quote'] . '" alt="Quote" border="0" title="' . _VIEW_QUOTE . '" />':_GEN_QUOTE);
                                }
                                else
                                {
                                    //user is not allowed to write a post
                                    if ($topicLocked == 1 || $forumLocked) {
                                        $msg_closed = _POST_LOCK_SET;
                                    }
                                    else {
                                        $msg_closed = _VIEW_DISABLED;
                                    }
                                }

                                $showedEdit = 0; //reset this value
                                //Offer an moderator the delete link
                                if ($is_Moderator)
                                {
                                    $msg_delete = CKunenaLink::GetTopicPostLink('delete', $catid, $fmessage->id , isset($kunenaIcons['delete']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['delete'] . '" alt="Delete" border="0" title="' . _VIEW_DELETE . '" />':_GEN_DELETE);
                                    $msg_merge = CKunenaLink::GetTopicPostLink('merge', $catid, $fmessage->id , isset($kunenaIcons['merge']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['merge'] . '" alt="' . _GEN_MERGE . '" border="0" title="' . _GEN_MERGE . '" />':_GEN_MERGE);
									// TODO: Enable split when it's fixed
                                    // $msg_split = CKunenaLink::GetTopicPostLink('split', $catid, $fmessage->id , isset($kunenaIcons['split']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['split'] . '" alt="' . _GEN_SPLIT . '" border="0" title="' . _GEN_SPLIT . '" />':_GEN_SPLIT);
                                }

                                if ($kunenaConfig->useredit && $kunena_my->id != "")
                                {
                                    //Now, if the viewer==author and the viewer is allowed to edit his/her own post then offer an 'edit' link
                                    $allowEdit = 0;
                                    if ($kunena_my->id == $userinfo->userid)
                                    {
                                        if(((int)$kunenaConfig->useredittime)==0)
                                        {
                                            $allowEdit = 1;
                                        }
                                        else
                                        {
                                            //Check whether edit is in time
                                            $modtime = $fmessage->modified_time;
                                            if(!$modtime)
                                            {
                                                $modtime = $fmessage->time;
                                            }
                                            if(($modtime + ((int)$kunenaConfig->useredittime)) >= CKunenaTools::kunenaGetInternalTime())
                                            {
                                                $allowEdit = 1;
                                            }
                                        }
                                    }
                                    if($allowEdit)
                                    {
                                        $msg_edit = CKunenaLink::GetTopicPostLink('edit', $catid, $fmessage->id , isset($kunenaIcons['edit']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['edit'] . '" alt="Edit" border="0" title="' . _VIEW_EDIT . '" />':_GEN_EDIT);
                                        $showedEdit = 1;
                                    }
                                }

                                if ($is_Moderator && $showedEdit != 1)
                                {
                                    //Offer a moderator always the edit link except when it is already showing..
                                    $msg_edit = CKunenaLink::GetTopicPostLink('edit', $catid, $fmessage->id , isset($kunenaIcons['edit']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['edit'] . '" alt="Edit" border="0" title="' . _VIEW_EDIT . '" />':_GEN_EDIT);
                                }

                                //(JJ)
                                if (file_exists(KUNENA_ABSTMPLTPATH . '/message.php')) {
                                    include (KUNENA_ABSTMPLTPATH . '/message.php');
                                }
                                else {
                                    include (KUNENA_PATH_TEMPLATE_DEFAULT .DS. 'message.php');
                                }

                                unset(
                                $msg_id,
                                $msg_username,
                                $msg_avatar,
                                $msg_usertype,
                                $msg_userrank,
                                $msg_userrankimg,
                                $msg_posts,
                                $msg_move,
                                $msg_karma,
                                $msg_karmaplus,
                                $msg_karmaminus,
                                $msg_ip,
                                $msg_ip_link,
                                $msg_date,
                                $msg_subject,
                                $msg_text,
                                $msg_signature,
                                $msg_reply,
                                $msg_birthdate,
                                $msg_quote,
                                $msg_edit,
                                $msg_closed,
                                $msg_delete,
                                $msg_sticky,
                                $msg_lock,
                                $msg_aim,
                                $msg_icq,
                                $msg_msn,
                                $msg_yim,
                                $msg_skype,
                                $msg_gtalk,
                                $msg_website,
                                $msg_yahoo,
                                $msg_buddy,
                                $msg_profile,
                                $msg_online,
                                $msg_pms,
                                $msg_loc,
                                $msg_regdate,
                                $msg_prflink,
                                $msg_location,
                                $msg_gender,
                                $msg_personal,
                                $myGraph);
                                $useGraph = 0;
                            } // end for
                        }
                        ?>
                    </td>
                </tr>

                <?php
                if ($view != "flat")
                {
                ?>

                    <tr>
                        <td>
                            <?php
                            if (file_exists(KUNENA_ABSTMPLTPATH . '/thread.php')) {
                                include (KUNENA_ABSTMPLTPATH . '/thread.php');
                            }
                            else {
                                include (KUNENA_PATH_TEMPLATE_DEFAULT .DS. 'thread.php');
                            }
                            ?>
                        </td>
                    </tr>

                <?php
                }
                ?>
            </table>


            <!-- B: List Actions Bottom -->
            <table class="kunena_list_actions_bottom" border = "0" cellspacing = "0" cellpadding = "0" width="100%">
                <tr>
                    <td class="kunena_list_actions_goto">
                        <?php
                        //go to top
                        echo '<a name="forumbottom" /> ';
                        echo CKunenaLink::GetSamePageAnkerLink('forumtop', isset($kunenaIcons['toparrow']) ? '<img src="' . KUNENA_URLICONSPATH . $kunenaIcons['toparrow'] . '" border="0" alt="' . _GEN_GOTOTOP . '" title="' . _GEN_GOTOTOP . '"/>' : _GEN_GOTOTOP);

			echo '</td>';

	if ($is_Moderator || isset($thread_reply) || isset($thread_subscribe) || isset($thread_favorite))
	{
	    echo '<td class="kunena_list_actions_forum">';
	    echo '<div class="kunena_message_buttons_row" style="text-align: center;">';
	    if (isset($thread_reply)) echo $thread_reply;
	    if (isset($thread_subscribe)) echo ' '.$thread_subscribe;
	    if (isset($thread_favorite)) echo ' '.$thread_favorite;
	    echo '</div>';
            if ($is_Moderator)
            {
		echo '<div class="kunena_message_buttons_row" style="text-align: center;">';
		echo $thread_delete;
		echo ' '.$thread_move;
		echo ' '.$thread_sticky;
		echo ' '.$thread_lock;
		echo '</div>';
	    }
            echo '</td>';
	}
	echo '<td class="kunena_list_actions_forum" width="100%">';
        if (isset($thread_new))
        {
	    echo '<div class="kunena_message_buttons_row" style="text-align: left;">';
	    echo $thread_new;
	    echo '</div>';
        }
        if (isset($thread_merge))
        {
	    echo '<div class="kunena_message_buttons_row" style="text-align: left;">';
	    echo $thread_merge;
	    echo '</div>';
	}
	echo '</td>';

        echo '<td class="kunena_list_pages_all" nowrap="nowrap">';
        echo $pagination;
        echo '</td>';
	echo '</tr></table>';
        echo '<div class = "'. $boardclass .'forum-pathway-bottom">';
	echo $pathway1;
	echo '</div>';
    ?>
	<!-- F: List Actions Bottom -->

	<!-- B: Category List Bottom -->

<table class="kunena_list_bottom" border = "0" cellspacing = "0" cellpadding = "0" width="100%">
    <tr>
      <td class="kunena_list_moderators">

                    <!-- Mod List -->

                        <?php
                        //get the Moderator list for display
                        $kunena_db->setQuery("SELECT m.*, u.* FROM #__kunena_moderation AS m LEFT JOIN #__users AS u ON u.id=m.userid WHERE m.catid={$catid}");
                        $modslist = $kunena_db->loadObjectList();
                        	check_dberror("Unable to load moderators.");
                        ?>

                        <?php
                        if (count($modslist) > 0)
                        { ?>
        <div class = "kunenabox-bottomarea-modlist">
          <?php
                            echo '' . _GEN_MODERATORS . ": ";

                          	$mod_cnt = 0;
                           	foreach ($modslist as $mod) {
				            	if ($mod_cnt) echo ', ';
			                	$mod_cnt++;
                                echo CKunenaLink::GetProfileLink($kunenaConfig, $mod->userid, ($kunenaConfig->username ? $mod->username : $mod->name));
                            } ?>
        </div>
        <?php  } ?>
        <!-- /Mod List -->
      </td>
      <td class="kunena_list_categories"> <?php
                    if ($kunenaConfig->enableforumjump)
                        require (KUNENA_PATH_LIB .DS. 'kunena.forumjump.php');
                    ?>
      </td>
    </tr>
</table>
	<!-- F: Category List Bottom -->

<?php
    }
}
else {
    echo _KUNENA_NO_ACCESS;
}

if ($kunenaConfig->highlightcode)
{
	echo '
	<script type="text/javascript" src="'.KUNENA_DIRECTURL . '/template/default/plugin/chili/jquery.chili-2.2.js"></script>
	<script id="setup" type="text/javascript">
	ChiliBook.recipeFolder     = "'.KUNENA_DIRECTURL . '/template/default/plugin/chili/";
	ChiliBook.stylesheetFolder     = "'.KUNENA_DIRECTURL . '/template/default/plugin/chili/";
	</script>
	';
}

?>
