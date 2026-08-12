<?php
/**
 * page_signout.php
 */
MyUser::logout();
PageEngine::goto(url("/"));
