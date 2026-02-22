<?php global $arch_footer;
if ($arch_footer) { ?>
    <ul id="archfooter">
        <?php
        foreach ($arch_footer as $arch_name => $arch_url) {
            echo '<li id="aft-' . strtolower($arch_name) . '"'
                . '><a href="' . $arch_url . '">' . $arch_name . '</a></li>';
        }
        ?>
    </ul>
<?php }
