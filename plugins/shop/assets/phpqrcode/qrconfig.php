<?php
/*
 * PHP QR Code encoder
 *
 * Config file, feel free to modify
 */
     
    define('QR_CACHEABLE', false); // patched: avoid filesystem writes in plugin assets
    define('QR_CACHE_DIR', sys_get_temp_dir().DIRECTORY_SEPARATOR); // patched
    define('QR_LOG_DIR', sys_get_temp_dir().DIRECTORY_SEPARATOR); // patched
    
    define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
    define('QR_FIND_FROM_RANDOM', false);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
    define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false
                                                  
    define('QR_PNG_MAXIMUM_SIZE',  1024);                                                       // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
                                                  