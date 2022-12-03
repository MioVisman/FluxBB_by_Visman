<?php
/***********************************************************************

  Copyright (C) 2022 Visman (mio.visman@yandex.ru)
  Copyright (C) 2010 Mpok (mpok@fluxbb.fr)
  based on code Copyright (C) 2005 Vincent Garnier (vin100@forx.fr)
  License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher

************************************************************************/

// Limits configuration / Configuration des limites
$smilies_config_image_size = 10240;	// max upload image size in bytes
$smilies_config_image_width = 20;			// max upload image width in pixels
$smilies_config_image_height = 20;		// max upload image height in pixels

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION', '1.4.3');
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));

// Load the smilies language files
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_smilies.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_smilies.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_smilies.php';

// Retrieve the smiley set
if (file_exists(FORUM_CACHE_DIR.'cache_smilies.php'))
	include FORUM_CACHE_DIR.'cache_smilies.php';
else
{
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_smiley_cache();
	require FORUM_CACHE_DIR.'cache_smilies.php';
}

// Retrieve the smiley images
$img_smilies = array();
$d = dir(PUN_ROOT.'img/smilies');
while (($entry = $d->read()) !== false)
{
	if ($entry != '.' && $entry != '..' && $entry != 'index.html')
		$img_smilies[] = $entry;
}
$d->close();
@natsort($img_smilies);

// Change smilies texts, images and positions
if (isset($_POST['reord']))
{
	$smilies_order = $_POST['smilies_order'] ?? null;
	$smilies_img = $_POST['smilies_img'] ?? null;
	$smilies_code = $_POST['smilies_code'] ?? null;

	if (! is_array($smilies_order) || ! is_array($smilies_img) || ! is_array($smilies_code)) {
		message($lang_common['Bad request'], false, '404 Not Found');
	}

	$smilies_order = array_map('intval', array_map('pun_trim', $smilies_order));
	$smilies_img = array_map('pun_trim', $smilies_img);
	$smilies_code = array_map('pun_trim', $smilies_code);

	// Checking smilies codes
	$smiley_dups = array();
	foreach ($smilies_code as $v)
	{
		if ($v == '')
			message($lang_smiley['Create Smiley Code None']);

		if (in_array($v, $smiley_dups))
			message(sprintf($lang_smiley['Duplicate smilies code'], pun_htmlspecialchars($v)));
		else
			$smiley_dups[] = $v;
	}

	$result = $db->query('SELECT id FROM '.$db->prefix.'smilies ORDER BY disp_position') or error('Unable to retrieve smilies', __FILE__, __LINE__, $db->error());

	// Update all smilies
	while ($db_smilies = $db->fetch_assoc($result)) {
		$id = $db_smilies['id'];

		if (isset($smilies_order[$id], $smilies_code[$id], $smilies_img[$id])) {
			$db->query('UPDATE '.$db->prefix.'smilies SET disp_position='.$smilies_order[$id].', text=\''.$db->escape($smilies_code[$id]).'\', image=\''.$db->escape($smilies_img[$id]).'\' WHERE id='.$id) or error('Unable to edit smilies', __FILE__, __LINE__, $db->error());
		}
	}

	// Regenerate cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_smiley_cache();

	redirect(PLUGIN_URL, $lang_smiley['Smilies edited']);
}

// Remove smilies
elseif (isset($_POST['remove']))
{
	if (empty($_POST['rem_smilies']) || ! is_array($_POST['rem_smilies']))
		message($lang_smiley['No Smileys']);
	$rem_smilies = array_map('intval', array_keys($_POST['rem_smilies']));

	// Delete smilies
	$db->query('DELETE FROM '.$db->prefix.'smilies WHERE id IN ('.implode(', ', $rem_smilies).')') or error('Unable to delete smiley', __FILE__, __LINE__, $db->error());

	// Regenerate cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_smiley_cache();

	redirect(PLUGIN_URL, $lang_smiley['Delete Smiley Redirect']);
}

// Add a smiley to the list
elseif (isset($_POST['add_smiley']))
{
	$smiley_code = pun_trim($_POST['smiley_code'] ?? '');
	$smiley_image = pun_trim($_POST['smiley_image'] ?? '');

	// Checking text code and image
	if ($smiley_code == '')
		message($lang_smiley['Create Smiley Code None']);
	if (in_array($smiley_code, array_keys($smilies)))
		message($lang_smiley['Code already exists']);
	if ($smiley_image == '')
		message($lang_smiley['Create Smiley Image None']);

	// Add the smiley
	$db->query('INSERT INTO '.$db->prefix.'smilies (image, text) VALUES (\''.$db->escape($smiley_image).'\', \''.$db->escape($smiley_code).'\')') or error('Unable to add smiley', __FILE__, __LINE__, $db->error());

	// Regenerate cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_smiley_cache();

	redirect(PLUGIN_URL, $lang_smiley['Successful Creation']);
}

// Delete images
elseif (isset($_POST['delete']))
{
	if (empty($_POST['del_smilies']) || ! is_array($_POST['del_smilies']))
		message($lang_smiley['No Images']);
	$del_smilies = array_map('pun_trim', $_POST['del_smilies']);

	$to_delete = $images_affected = $not_deleted = array();

	// Checking if images to delete are used by some smilies
	$smiley_img = array_unique(array_values($smilies));
	foreach (array_keys($del_smilies) as $img)
	{
		if (!in_array($img, $smiley_img))
			$to_delete[] = $img;
		else
			$images_affected[] = $img;
	}

	if (!empty($images_affected))
		message(sprintf($lang_smiley['Images affected'], pun_htmlspecialchars(implode(', ', $images_affected))));
	else
	{
		// Delete each image
		foreach ($to_delete as $img)
		{
			$img = preg_replace('%(?:\.\.|[^\.\w-])%', '-', $img);
			if (!@unlink(PUN_ROOT.'img/smilies/'.$img))
				$not_deleted[] = $img;
		}
	}

	if (!empty($not_deleted))
		$message = sprintf($lang_smiley['Images not deleted'], pun_htmlspecialchars(implode(', ', $not_deleted)));
	else
		$message = $lang_smiley['Images deleted'];

	redirect(PLUGIN_URL, $message);
}

// Add an image
elseif (isset($_POST['add_image']))
{
	if (!isset($_FILES['req_file']))
		message($lang_smiley['No file']);

	$uploaded_file = $_FILES['req_file'];

	// Make sure the upload went smooth
	if (isset($uploaded_file['error']))
	{
		switch ($uploaded_file['error'])
		{
			case 1:	// UPLOAD_ERR_INI_SIZE
			case 2:	// UPLOAD_ERR_FORM_SIZE
				message($lang_smiley['Too large ini']);
				break;

			case 3:	// UPLOAD_ERR_PARTIAL
				message($lang_smiley['Partial upload']);
				break;

			case 4:	// UPLOAD_ERR_NO_FILE
				message($lang_smiley['No file']);
				break;

			case 6:	// UPLOAD_ERR_NO_TMP_DIR
				message($lang_smiley['No tmp directory']);
				break;

			default:
				// No error occured, but was something actually uploaded?
				if ($uploaded_file['size'] == 0)
					message($lang_smiley['No file']);
				break;
		}
	}

	if (is_uploaded_file($uploaded_file['tmp_name']))
	{
		include PUN_ROOT.'include/upload.php';

		// Make sure the file isn't too big
		if ($uploaded_file['size'] > $smilies_config_image_size) {
			message($lang_smiley['Too large'].' '.$smilies_config_image_size.' '.$lang_smiley['bytes'].'.');
		}

		if (false === $upf_class->loadFile($uploaded_file['tmp_name'], $uploaded_file['name'])) {
			message($lang_up['Unknown failure'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')');
		}

		if (true !== $upf_class->isImage() || ! in_array($upf_class->getFileExt(), ['jpg', 'gif', 'png'])) {
			message($lang_smiley['Bad type']);
		}

		if (false !== $upf_class->isUnsafeContent()) {
			message($lang_up['Error inject']);
		}

		$upf_class->prepFileName();

		if (false === $upf_class->loadImage()) {
			message($lang_up['Error img'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')');
		}

		$filename = $upf_class->getFileName();
		// Determine type
		$extensions = null;
		switch ($upf_class->getFileExt()) {
			case 'gif':
				$extensions = array('.gif', '.jpg', '.png');
				break;
			case 'jpg':
				$extensions = array('.jpg', '.gif', '.png');
				break;
			case 'png':
				$extensions = array('.png', '.gif', '.jpg');
				break;
			default:
				message($lang_smiley['Bad type']);
		}

		$fileinfo = $upf_class->saveFile(PUN_ROOT . 'img/smilies/' . $filename . '.tmp', true);
		if (false === $fileinfo) {
			message($lang_smiley['Move failed'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')');
		}

		// Now check the width/height
		list($width, $height, $type,) = getimagesize(PUN_ROOT.'img/smilies/'.$filename.'.tmp');
		if (empty($width) || empty($height) || $width > $smilies_config_image_width || $height > $smilies_config_image_height)
		{
			@unlink(PUN_ROOT.'img/smilies/'.$filename.'.tmp');
			message($lang_smiley['Too wide or high'].' '.$smilies_config_image_width.'x'.$smilies_config_image_height.' '.$lang_smiley['pixels'].'.');
		}
		else if ($type == 1 && $uploaded_file['type'] != 'image/gif')			// Prevent dodgy uploads
		{
			@unlink(PUN_ROOT.'img/smilies/'.$filename.'.tmp');
			message($lang_smiley['Bad type']);
		}

		// Delete any old images and put the new one in place
		@unlink(PUN_ROOT.'img/smilies/'.$filename.$extensions[0]);
		@unlink(PUN_ROOT.'img/smilies/'.$filename.$extensions[1]);
		@unlink(PUN_ROOT.'img/smilies/'.$filename.$extensions[2]);
		@rename(PUN_ROOT.'img/smilies/'.$filename.'.tmp', PUN_ROOT.'img/smilies/'.$filename.$extensions[0]);
		@chmod(PUN_ROOT.'img/smilies/'.$filename.$extensions[0], 0644);
	}
	else
		message($lang_smiley['Unknown failure']);

	redirect(PLUGIN_URL, $lang_smiley['Successful Upload']);
}

// Displaying
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span>Plugin Smilies v.<?php echo PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_smiley['Description'] ?></p>
			</div>
		</div>
	</div>
	<div class="blockform">
		<h2 class="block2"><span><?php echo $lang_smiley['Current Smilies'] ?></span></h2>
		<div class="box">
	<?php

	$result = $db->query('SELECT * FROM '.$db->prefix.'smilies ORDER BY disp_position') or error('Unable to retrieve smilies', __FILE__, __LINE__, $db->error());
	$db_smilies = $db->fetch_assoc($result);

	if (is_array($db_smilies))
	{

?>
			<form method="post" action="<?php echo PLUGIN_URL ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_smiley['List Current Smilies'] ?></legend>
						<div class="infldset">
							<table>
								<thead><tr>
									<th scope="col"><?php echo $lang_smiley['Position'] ?></th>
									<th scope="col"><?php echo $lang_smiley['Image Filename'] ?></th>
									<th scope="col"><?php echo $lang_smiley['Code'] ?></th>
									<th scope="col"><?php echo $lang_smiley['Image'] ?></th>
									<th scope="col"><?php echo $lang_smiley['Remove'] ?></th>
								</tr></thead>
								<tbody>
<?php

		do
		{

?>
									<tr>
										<th scope="row"><input type="text" name="smilies_order[<?php echo $db_smilies['id'] ?>]" value="<?php echo $db_smilies['disp_position'] ?>" size="4" maxlength="4" /></th>
										<td><select name="smilies_img[<?php echo $db_smilies['id'] ?>]">
<?php

			foreach ($img_smilies as $img)
			{
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option';
				if ($img == $db_smilies['image'])
					echo ' selected="selected"';
				echo ' value="'.pun_htmlspecialchars($img).'">'.pun_htmlspecialchars($img).'</option>'."\n";
			}

?>
										</select></td>
										<td><input type="text" name="smilies_code[<?php echo $db_smilies['id'] ?>]" value="<?php echo pun_htmlspecialchars($db_smilies['text']) ?>" size="12" maxlength="60" /></td>
										<td><img src="img/smilies/<?php echo pun_htmlspecialchars($db_smilies['image']) ?>" alt="<?php echo pun_htmlspecialchars($db_smilies['text']) ?>" /></td>
										<td><input name="rem_smilies[<?php echo $db_smilies['id'] ?>]" type="checkbox" value="1" /></td>
									</tr>
<?php

		}
		while ($db_smilies = $db->fetch_assoc($result));

?>
								</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input name="reord" type="submit" value="<?php echo $lang_smiley['Edit smilies'] ?>" /> <input name="remove" type="submit" value="<?php echo $lang_smiley['Remove Selected'] ?>" /></p>
			</form>
<?php

	}
	else
	{

?>
			<div class="fakeform">
				<div class="inbox">
					<p><?php echo $lang_smiley['No smiley'] ?></p>
				</div>
			</div>
<?php

	}

?>
			<form method="post" action="<?php echo PLUGIN_URL ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_smiley['Submit New Smiley'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<th scope="row"><?php echo $lang_smiley['Smiley Code'] ?></th>
									<td>
										<input type="text" name="smiley_code" size="25" />
										<span><?php echo $lang_smiley['Smiley Code Description'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_smiley['Smiley Image'] ?></th>
									<td>
										<select name="smiley_image">
											<option selected="selected" value=""><?php echo $lang_smiley['Choose Image'] ?></option>
<?php

	foreach ($img_smilies as $img)
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.pun_htmlspecialchars($img).'">'.pun_htmlspecialchars($img).'</option>'."\n";

?>
										</select>
										<span><?php echo $lang_smiley['Smiley Image Description'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_smiley" value="<?php echo $lang_smiley['Submit Smiley'] ?>" /></p>
			</form>
		</div>
		<h2 class="block2"><span><?php echo $lang_smiley['Current Images'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?php echo PLUGIN_URL ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_smiley['List Images Smilies'] ?></legend>
						<div class="infldset">
							<table>
								<thead><tr>
									<th scope="col"><?php echo $lang_smiley['Image Filename']; ?></th>
									<th scope="col"><?php echo $lang_smiley['Image']; ?></th>
									<th scope="col"><?php echo $lang_smiley['Delete']; ?></th>
								</tr></thead>
								<tbody>
<?php

	foreach ($img_smilies as $img)
	{
		$img = pun_htmlspecialchars($img);

?>
									<tr>
										<th scope="row"><?php echo $img ?></th>
										<td><img src="img/smilies/<?php echo $img ?>" alt="" /></td>
										<td><input name="del_smilies[<?php echo $img ?>]" type="checkbox" value="1" /></td>
									</tr>
<?php

	}

?>
								</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input name="delete" type="submit" value="<?php echo $lang_smiley['Delete Selected'] ?>" /></p>
			</form>
			<form method="post" enctype="multipart/form-data" action="<?php echo PLUGIN_URL ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_smiley['Add Images Smilies'] ?></legend>
						<div class="infldset">
							<label><?php echo $lang_smiley['Image file'] ?>&#160;&#160;<input name="req_file" type="file" size="40" /></label>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input name="add_image" type="submit" value="<?php echo $lang_smiley['Upload'] ?>" /></p>
			</form>
		</div>
	</div>
<?php
}
