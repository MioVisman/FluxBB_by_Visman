<?php
/**
 * Copyright (C) 2011-2020 Visman (visman@inbox.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (! defined('PUN')) {
	exit;
}

if (!$pun_user['is_guest'] && isset($pun_config['o_upload_config'], $required_fields['req_message'])) {
	if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_up_limit'] > 0 && $pun_user['g_up_max'] > 0)) {
		// Load language file
		if (! isset($lang_up)) {
			if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/upload.php')) {
				require PUN_ROOT.'lang/'.$pun_user['language'].'/upload.php';
			} else {
				require PUN_ROOT.'lang/English/upload.php';
			}
		}

		if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/upfiles.css')) {
			$style = 'style/'.$pun_user['style'].'/upfiles.css';
		} else {
			$style = 'style/imports/upfiles.css';
		}

		$upf_conf = unserialize($pun_config['o_upload_config']);
		$upf_max_size = (int) (10485.76 * $pun_user['g_up_max'])

?>
<script type="text/javascript">
/* <![CDATA[ */
if (typeof FluxBB === 'undefined' || !FluxBB) {var FluxBB = {};}
FluxBB.uploadvars = {
	action: 'upfiles.php',
	style: '<?= addslashes($style) ?>',
	lang: {
		upfiles: '<strong><?= addslashes($lang_up['upfiles']) ?></strong>',
		confirmation: '<?= addslashes($lang_up['delete file']) ?>',
		large: '<?= addslashes($lang_up['Too large']) ?>',
		bad_type: '<?= addslashes($lang_up['Bad type']) ?>'
	},
	maxsize: <?= $upf_max_size ?>,
	exts: ['<?= str_replace([' ', ','], ['', '\', \''], addslashes($pun_user['g_up_ext'])) ?>'],
	token: '<?= addslashes(function_exists('csrf_hash') ? csrf_hash('upfiles.php') : pun_csrf_token()) ?>'
};
/* ]]> */
</script>
<script type="text/javascript" src="js/upload.js"></script>

<div id="upf-template" style="width: 0; height: 0; overflow: hidden; margin: 0; padding: 0;">
	<div class="inform upf-fmess">
		<fieldset>
			<legend><?= $lang_up['upfiles'] ?></legend>
			<div class="infldset">
				<button id="upf-button" type="button"><?= $lang_up['fichier'] ?></button>
				<span><?= sprintf($lang_up['info_2'], pun_htmlspecialchars(str_replace([' ', ','], ['', ', '], $pun_user['g_up_ext'])), pun_htmlspecialchars(file_size($upf_max_size))) ?></span>
			</div>
		</fieldset>
	</div>
	<div class="inform upf-fmess">
		<fieldset id="upf-list-fls">
			<div class="infldset">
				<div id="upf-container">
					<ul id="upf-list">
						<li id="upf--">
							<div class="upf-name" title="End">
								<span>&#160;</span>
							</div>
							<div class="upf-file" style="height: <?= max(intval($upf_conf['thumb_size']), 100) ?>px;">
								<a>
									<span>&#160;</span>
								</a>
							</div>
							<div class="upf-size">
								<span>&#160;</span>
							</div>
							<div class="upf-but upf-delete">
								<a title="<?= $lang_up['delete'] ?>">
									<span></span>
								</a>
							</div>
							<div class="upf-but upf-insert">
								<a title="<?= $lang_up['insert'] ?>">
									<span></span>
								</a>
							</div>
							<div class="upf-but upf-insert-t">
								<a title="<?= $lang_up['insert_thumb'] ?>">
									<span></span>
								</a>
							</div>
						</li>
					</ul>
				</div>
			</div>
		</fieldset>
	</div>
	<div class="inform upf-fmess">
		<fieldset>
			<div class="infldset">
				<div id="upf-legend">
					<div style="background-color: rgb(0, 255, 0); width: 0%;"><span>0%</span></div>
				</div>
				<p id="upf-legend-p"><?= sprintf($lang_up['info_4'], 0, pun_htmlspecialchars(file_size(1048576 * $pun_user['g_up_limit']))) ?></p>
			</div>
		</fieldset>
	</div>
</div>

<?php

	}
}
