<?php
i18n::init(__FILE__);
PageEngine::html("header", ["title" => __("Help")]);
?>
<div class="row"><div class="col-lg-8">
  <h1 class="h4"><?= html(__("Markdown reference")) ?></h1>
  <table class="table table-sm align-middle">
    <thead><tr><th><?= html(__("You type")) ?></th><th><?= html(__("You get")) ?></th></tr></thead>
    <tbody>
      <tr><td><code>**bold**</code></td><td><strong>bold</strong></td></tr>
      <tr><td><code>*italic*</code></td><td><em>italic</em></td></tr>
      <tr><td><code>~~struck~~</code></td><td><del>struck</del></td></tr>
      <tr><td><code>`inline code`</code></td><td><code>inline code</code></td></tr>
      <tr><td><code>[text](https://example.com)</code></td><td><a href="#">text</a></td></tr>
      <tr><td><code>![alt](https://example.com/i.png)</code></td><td><?= html(__("an image")) ?></td></tr>
      <tr><td><code>&gt; quote</code></td><td><?= html(__("a block quote")) ?></td></tr>
      <tr><td><code>- item</code></td><td><?= html(__("a list")) ?></td></tr>
      <tr><td><code>1. item</code></td><td><?= html(__("a numbered list")) ?></td></tr>
      <tr><td><code>### heading</code></td><td><?= html(__("a heading")) ?></td></tr>
      <tr><td><code>```php … ```</code></td><td><?= html(__("a code block")) ?></td></tr>
      <tr><td><code>| a | b |</code></td><td><?= html(__("a table")) ?></td></tr>
      <tr><td><code>@name</code></td><td><?= html(__("mentions someone")) ?></td></tr>
    </tbody>
  </table>
  <p class="small text-secondary"><?= html(__("HTML in posts is shown as text, not rendered. That keeps everyone safe.")) ?></p>

  <h2 class="h5 mt-4"><?= html(__("Karma thresholds")) ?></h2>
  <table class="table table-sm">
    <tbody>
      <?php foreach ([
          __("Comment everywhere") => Config::int("threshold_comment"),
          __("Upvote")             => Config::int("threshold_vote_up"),
          __("Flag posts")         => Config::int("threshold_flag"),
          __("Edit wiki posts")    => Config::int("threshold_edit_wiki"),
          __("Downvote")           => Config::int("threshold_vote_down"),
          __("Vote to close")      => Config::int("threshold_close_vote"),
          __("Edit tag wikis")     => Config::int("threshold_tag_wiki"),
          __("Edit anything")      => Config::int("threshold_edit_others"),
          __("Vote to delete")     => Config::int("threshold_delete_vote"),
      ] as $label => $value) { ?>
        <tr><td><?= html($label) ?></td><td class="text-end fw-semibold"><?= number_format($value) ?></td></tr>
      <?php } ?>
    </tbody>
  </table>

  <h2 class="h5 mt-4"><?= html(__("Search operators")) ?></h2>
  <ul class="small">
    <li><code>tag:php</code> &ndash; <?= html(__("only questions with that tag")) ?></li>
    <li><code>user:12</code> &ndash; <?= html(__("only questions by that member")) ?></li>
    <li><code>answers:0</code> &ndash; <?= html(__("only unanswered questions")) ?></li>
    <li><code>is:accepted</code> &ndash; <?= html(__("only solved questions")) ?></li>
    <li><code>score:5</code> &ndash; <?= html(__("minimum score")) ?></li>
  </ul>
</div></div>
<?php PageEngine::html("footer"); ?>
