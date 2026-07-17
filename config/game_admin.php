<?php
return ['emails'=>array_values(array_filter(array_map('trim',explode(',',env('DRAGONHERO_ADMIN_EMAILS','')))))];
