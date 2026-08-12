<?php
i18n::init(__FILE__);
PageEngine::html("header", ["title" => __("Privacy")]);
?>
<div class="row"><div class="col-lg-8">
  <h1 class="h4"><?= html(__("Privacy")) ?></h1>
  <p class="text-secondary small"><?= html(__("This is a template. Adapt it to your jurisdiction before you go live.")) ?></p>

  <h2 class="h6 mt-4"><?= html(__("What we store")) ?></h2>
  <ul>
    <li><?= html(__("Your user name, email address and anything you choose to put in your profile.")) ?></li>
    <li><?= html(__("The questions, answers, comments and votes you post.")) ?></li>
    <li><?= html(__("A salted hash of your IP address instead of the address itself, for spam protection.")) ?></li>
    <li><?= html(__("A session cookie, and an optional long lived cookie if you ask to stay signed in.")) ?></li>
  </ul>

  <h2 class="h6 mt-4"><?= html(__("What we do not do")) ?></h2>
  <ul>
    <li><?= html(__("No advertising or tracking cookies.")) ?></li>
    <li><?= html(__("No selling or sharing of your data.")) ?></li>
    <li><?= html(__("Avatars come from Gravatar based on a hash of your email address; disable it by uploading your own avatar.")) ?></li>
  </ul>

  <h2 class="h6 mt-4"><?= html(__("Your rights")) ?></h2>
  <p><?= html(__("You can export or delete your account data at any time - contact an administrator through the contact address of this site.")) ?></p>
</div></div>
<?php PageEngine::html("footer"); ?>
