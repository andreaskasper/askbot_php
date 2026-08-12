<?php
/**
 * footer.php - closes the document and loads the shared scripts.
 */
?>
</main>

<footer class="border-top mt-5 py-4 bg-body-tertiary">
  <div class="container small">
    <div class="row gy-3">
      <div class="col-md-4">
        <strong><?= html(Config::get("site_title")) ?></strong>
        <p class="text-secondary mb-0"><?= html(Config::get("site_tagline")) ?></p>
      </div>
      <div class="col-md-4">
        <ul class="list-unstyled mb-0">
          <li><a class="link-secondary" href="<?= htmlattr(url("about")) ?>"><?= html(__("About")) ?></a></li>
          <li><a class="link-secondary" href="<?= htmlattr(url("faq")) ?>"><?= html(__("FAQ")) ?></a></li>
          <li><a class="link-secondary" href="<?= htmlattr(url("help")) ?>"><?= html(__("Help")) ?></a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <ul class="list-unstyled mb-0">
          <li><a class="link-secondary" href="<?= htmlattr(url("privacy")) ?>"><?= html(__("Privacy")) ?></a></li>
          <li><a class="link-secondary" href="<?= htmlattr(url("terms")) ?>"><?= html(__("Terms")) ?></a></li>
          <li><a class="link-secondary" href="<?= htmlattr(url("feeds/questions.rss")) ?>"><i class="fa-solid fa-rss me-1"></i><?= html(__("RSS")) ?></a></li>
        </ul>
      </div>
    </div>
    <hr>
    <div class="d-flex flex-wrap justify-content-between text-secondary">
      <span>
        <?= html(__("Powered by")) ?>
        <a class="link-secondary" href="https://github.com/andreaskasper/askbot_php" rel="noopener">askbot_php <?= html(Askbot::VERSION) ?></a>
      </span>
      <span><?= html(sprintf(__("rendered in %s ms"), number_format(PageEngine::runtime() * 1000, 1))) ?><?php
        if (($_ENV["STAGE"] ?? "") === "development") { echo " · " . (int)SQL::$counter . " " . html(__("queries")); }
      ?></span>
    </div>
  </div>
</footer>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastArea"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.13/dist/vue.global.prod.js"></script>
<script>
window.askbot = {
  baseUrl: <?= json_encode(Config::baseUrl()) ?>,
  csrfToken: <?= json_encode(Csrf::token()) ?>,
  user: <?= json_encode(MyUser::isLoggedIn() ? ["id" => MyUser::id(), "name" => MyUser::name(), "karma" => MyUser::karma(), "isModerator" => MyUser::isModerator()] : null) ?>,
  i18n: <?= json_encode([
      "signInToVote" => __("Please sign in to vote."),
      "networkError" => __("Network problem. Please try again."),
      "saved"        => __("Saved."),
      "confirm"      => __("Are you sure?"),
  ]) ?>
};
</script>
<script src="<?= htmlattr(asset("js/askbot.js")) ?>"></script>
<?php if (!empty($params["scripts"])) { foreach ((array)$params["scripts"] as $script) { ?>
<script src="<?= htmlattr(asset($script)) ?>"></script>
<?php } } ?>
</body>
</html>
