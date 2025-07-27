<?php
if($_inCodeBlock) echo html($attrs['content']);
else echo $attrs['content'];
