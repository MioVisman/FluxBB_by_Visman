<?php global $arch_home, $arch_navbar, $arch_navbar_selected; ?>
<div id="archnavbar" class="anb-forum">
	<div id="archnavbarlogo"><h1><a href="<?php if (isset($arch_home)) { echo $arch_home; } ?>">Arch Linux</a></h1></div>
	<div id="archnavbarmenu">
		<ul id="archnavbarlist">
			<?php
			if (isset($arch_navbar)) {
				foreach ($arch_navbar as $arch_name => $arch_url) {
					echo '<li id="anb-'.strtolower($arch_name).'"'
						.(isset($arch_navbar_selected) && $arch_navbar_selected == $arch_name ? ' class="anb-selected"' : '')
						.'><a href="'.$arch_url.'">'.$arch_name.'</a></li>';
				}
			}
			?>
		</ul>
	</div>
</div>
