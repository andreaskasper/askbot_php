<?php
i18n::init(__FILE__);
PageEngine::html("header", ["title" => __("Terms")]);
?>
<div class="row"><div class="col-lg-8">
  <h1 class="h4"><?= html(__("Terms of use")) ?></h1>
  <p class="text-secondary small"><?= html(__("This is a template. Adapt it before you go live.")) ?></p>
  <ul>
    <li><?= html(__("Be civil. Attack arguments, never people.")) ?></li>
    <li><?= html(__("Post only content you are allowed to share.")) ?></li>
    <li><?= html(__("No spam, no advertising, no automated posting.")) ?></li>
    <li><?= html(__("Contributions are licensed to this community so they can stay readable for everyone.")) ?></li>
    <li><?= html(__("Moderators may edit, close or remove content that breaks these rules.")) ?></li>
  </ul>
</div></div>
<?php PageEngine::html("footer"); ?>
