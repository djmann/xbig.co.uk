<?php
// Simple script to read date-sorted information on XBIG reviews
require '../initialise.php';

$validated = FALSE;

$session_obj	= new session_manager(TRUE);
$user_id	= $session_obj->get ('user_id', FALSE);

$game_list	= FALSE;

if ($user_id)
	$validated = TRUE;

$session_obj->finish();

if ($validated == FALSE)
	redirect ('index.php');

if ($validated)
{
	try
	{
		$dbconn = xbig_dbconn ();

		$user_obj 	= new user_template ($dbconn, $user_id);
		$loginid 	= $user_obj->get ('loginid');
		$last_login 	= $user_obj->get ('last_login');

		// 1: Get a list of unreviewed games, oldest first
		$qu = "select g.id, g.blog_id, g.xbig_id, g.name, str_to_date(gt2.value, '%D %b %Y') release_date from games g, game_tags gt, game_tags gt2 " .
			"where g.id = gt.game_id and g.id = gt2.game_id and gt.name='reviewed' and gt.value='Unreviewed' and gt2.name='release_date'" .
			"order by str_to_date(gt2.value, '%D %b %Y') asc, g.name";
		$unreview_list = get_all_resultsets ($dbconn, $qu);


		// 2: Get a list of recently published games, most recent first
		$qu = "select g.id, g.blog_id, g.name, str_to_date(gt.value, '%D %b %Y') release_date from games g, game_tags gt " .
			"where g.id = gt.game_id and gt.name='release_date' " .
			"order by str_to_date(gt.value, '%D %b %Y') desc, g.name limit 50";
		$release_list = get_all_resultsets ($dbconn, $qu);

		// 3: Get a list of recent reviews, most recent first
		if ($session_obj->get ('role', FALSE) == 'superuser')
			$qu = "select gr.id from game_reviews gr order by gr.last_updated desc limit 10";
		else
			$qu = "select gr.id from game_reviews gr where user_id = $user_id order by gr.last_updated desc limit 10";

		// QND hack: create an instance of the review entity and stick it into the array...
		$review_list = get_all_resultsets ($dbconn, $qu);
		foreach ($review_list as &$review)
		{
			$review['entity'] = new review_template ($dbconn, $review['id']);
		}

		// 4: Get a list of news articles, most recent first
		if ($session_obj->get ('role', FALSE) == 'superuser')
			$qu = "select un.id from user_news un order by un.last_updated desc limit 10";
		else
			$qu = "select un.id from user_news un where user_id = $user_id order by un.last_updated desc limit 10";

		$news_list = get_all_resultsets ($dbconn, $qu);
		foreach ($news_list as &$news)
			$news['entity'] = new news_template ($dbconn, $news['id']);

		// 5: Get a list of recent comments, most recent first
		$qu = "select gc.id from guest_comments gc order by gc.created desc limit 20";

		$comments_list = get_all_resultsets ($dbconn, $qu);
		foreach ($comments_list as &$comment)
			$comment['entity'] = new comment_template ($dbconn, $comment['id']);

		$dbconn->close();
	}
	catch (Exception $e_obj)
	{
		throw ($e_obj);
	}
}
?>
<html>
<head>
<title>XBIG game manager: view content lists</title>
<script>
function load_game ()
{
	var blog_id = document.forms[0].elements["blog_id"].value;
	window.location = "edit_game.php?blog_id=" + blog_id;
}
</script>
</head>

<body>

<form method='POST' action='javascript: load_game()'>
<center>
<table width='100%'>
<tr>
	<td style='font-size: small' width='30%'>
	<a href='index.php'>logout</a>
	</td>

	<td width='40%' align='center'>
	<table style='border: solid black 1px;'>
	<tr>
		<th align='left'>Game's blog_id:</th>
		<td><input name='blog_id' type='text'></td>
		<td><input type='button' value='Edit' onClick='load_game ()'></td>
	</tr>
	</table>
	</td>

	<td style='font-size: small' width='30%' align='right'>
	<table><tr><td style='font-size: small'>
	<b>Loginid:</b> <? print $loginid ?><br />
	<b>Last login:</b> <? print $last_login ?>
	</td></tr></table>
	</td>
</tr>
</table>

<table border='1'>
<tr>
	<th>Unreviewed Games [<? print sizeof ($unreview_list) ?>]</th>
	<th>Recent Releases [<? print sizeof ($release_list) ?>]</th>
	<th>Recent Reviews [<? print sizeof ($review_list) ?>]</th>
	<th>Recent News</th>
	<th>Recent Comments</th>
</tr>
<tr>
	<td align='center' valign='top'>
	<table>
	<?php
	$i = 1;
	foreach ($unreview_list as $game)
	{
		print "<tr>\n";
			print "<td valign='top'>$i)</td>\n";
			print "<td valign='top' style='font-size: small' >";
			print "<b><a href='edit_game.php?blog_id={$game['blog_id']}'>{$game['name']}</a></b><br />\n";
			print "{$game['release_date']} - [<a target='_new{$i}' href='http://marketplace.xbox.com/en-US/Product/{$game['xbig_id']}'>xbox.com</a>]\n";
			print "</td>\n";
		print "</tr>\n";
		$i++;
	}
	?>
	</table>
	</td>

	<td align='center' valign='top'>
	<table>
<?php
	foreach ($release_list as $game)
	{
		print "<tr>\n";
			print "<td style='font-size: small' >";
			print "<b><a href='edit_game.php?blog_id={$game['blog_id']}'>{$game['name']}</a></b><br />\n";
			print "{$game['release_date']}\n";
			print "</td>\n";
		print "</tr>\n";
	}
?>
	</table>
	</td>

	<td align='center' valign='top'>
	<table cellpadding='0' cellspacing='0'> <?php
	for ($i = 0; $i < sizeof ($review_list); $i++)
	{
		// For some reason (possibly because $review was being used as a pointer earlier)
		// $review = $review_list[$i] was overwriting the last element in the array with
		// the preceeding value (i.e. $list[N] became equal to $list[N-1]
		// Not sure how/why this happened, but the fix is relatively simple:
		// we just give the variable a different name!
		// TODO: see if we can reproduce this behaviour with a small test script...
		$t_obj = $review_list[$i];
		$r_obj = $t_obj['entity'];

		print "<tr>\n";
			print "<td style='font-size: small' align='left' valign='top'>" .
				"<b><a href='edit_game.php?blog_id={$r_obj->get('blog_id')}' title=\"{$r_obj->render_to_text (200)}\">" .	
				$r_obj->get('game_name')	. "</a></b><br />\n" .
				$r_obj->get('last_updated') 	. "<br />\n" .
				"[<b><font color='#CC00CC'>" . 	$r_obj->get('loginid') 	. "</font></b> |\n" .
				"<b>" .	$r_obj->get('recommendation') 	. "</b> | " .
		 		$r_obj->get('status') 		. "]<br />\n" .
				"</td>\n";
		print "</tr>\n";

		print "<tr><td>&nbsp;</td></tr>\n";
	}
	?> </table>
	</td>

	<td valign='top' align='center'>
	<a href='edit_news.php'>[CREATE]</a><br />

	<table cellpadding='0' cellspacing='0'> <?php
	foreach ($news_list as $news)
	{
		$r_obj = $news['entity'];

		print "<tr>\n";
			print "<td style='font-size: small' align='left' valign='top'>" .
				"<b><a href='edit_news.php?news_id={$r_obj->get('id')}' title=\"{$r_obj->render_to_text (200)}\">" .	
						$r_obj->get('headline')		. "</a></b><br />\n" .
						$r_obj->get('last_updated') 	. "<br />\n" .
				"<b>" . 	$r_obj->get('loginid') 		. "</b><br />\n" .
				"[" . 		$r_obj->get('status') 		. "]<br />\n" .
				"</td>\n";
		print "</tr>\n";

		print "<tr><td>&nbsp;</td></tr>\n";
	}
	?> </table>
	</td>
	
</tr>
</table>


</center>
</body>
</html>
