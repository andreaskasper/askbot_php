<?php
/**
 * admin/page_moderation.php - the moderator queue.
 */
i18n::init(__FILE__);

$queue = Moderation::queue();

PageEngine::html("header", ["title" => __("Moderation"), "noindex" => true]);
PageEngine::html("admin/box_nav", ["active" => "moderation"]);
?>
<div id="modApp" v-cloak>
  <h1 class="h4 mb-3"><?= html(__("Moderation queue")) ?></h1>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link" :class="{active: tab === 'flags'}" href="#" @click.prevent="tab = 'flags'">
      <?= html(__("Flags")) ?> <span class="badge text-bg-danger"><?= count($queue["flags"]) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" :class="{active: tab === 'close'}" href="#" @click.prevent="tab = 'close'">
      <?= html(__("Close votes")) ?> <span class="badge text-bg-secondary"><?= count($queue["close_votes"]) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" :class="{active: tab === 'spam'}" href="#" @click.prevent="tab = 'spam'">
      <?= html(__("Hidden posts")) ?> <span class="badge text-bg-secondary"><?= count($queue["spam_posts"]) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" :class="{active: tab === 'suspended'}" href="#" @click.prevent="tab = 'suspended'">
      <?= html(__("Suspended")) ?> <span class="badge text-bg-secondary"><?= count($queue["suspended"]) ?></span></a></li>
  </ul>

  <div v-show="tab === 'flags'">
    <?php if ($queue["flags"] === []) { ?>
      <p class="text-secondary"><?= html(__("Nothing to review. Good sign.")) ?></p>
    <?php } else { ?>
      <table class="table table-sm align-middle">
        <thead><tr><th><?= html(__("Post")) ?></th><th><?= html(__("Reason")) ?></th><th><?= html(__("By")) ?></th><th><?= html(__("When")) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($queue["flags"] as $flag) { ?>
          <tr v-if="!handled.includes(<?= (int)$flag["id"] ?>)">
            <td><a href="<?= htmlattr(Post::permalink((string)$flag["post_type"], (int)$flag["post_id"])) ?>" target="_blank">
              <?= html($flag["post_type"]) ?> #<?= (int)$flag["post_id"] ?></a></td>
            <td><?= html(Flag::REASONS[(string)$flag["reason"]] ?? (string)$flag["reason"]) ?>
              <?php if (($flag["note"] ?? "") !== "") { ?><div class="small text-secondary"><?= html($flag["note"]) ?></div><?php } ?></td>
            <td class="small"><?= html((string)($flag["username"] ?? "-")) ?></td>
            <td class="small text-secondary"><?= html(i18n::ago((string)$flag["created_at"])) ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-success" @click="handleFlag(<?= (int)$flag["id"] ?>, 'accepted')"><?= html(__("Valid")) ?></button>
              <button class="btn btn-sm btn-outline-secondary" @click="handleFlag(<?= (int)$flag["id"] ?>, 'declined')"><?= html(__("Decline")) ?></button>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    <?php } ?>
  </div>

  <div v-show="tab === 'close'">
    <table class="table table-sm">
      <tbody>
      <?php foreach ($queue["close_votes"] as $row) { ?>
        <tr>
          <td><a href="<?= htmlattr(Question::permalink((int)$row["question_id"])) ?>" target="_blank"><?= html($row["title"]) ?></a></td>
          <td class="text-secondary"><?= html($row["action"]) ?></td>
          <td class="text-end"><?= (int)$row["votes"] ?>/<?= Config::int("close_votes_needed", 3) ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>

  <div v-show="tab === 'spam'">
    <table class="table table-sm">
      <tbody>
      <?php foreach ($queue["spam_posts"] as $row) { ?>
        <tr>
          <td><a href="<?= htmlattr(Question::permalink((int)$row["id"])) ?>" target="_blank"><?= html($row["title"]) ?></a></td>
          <td class="text-end text-secondary"><?= html(i18n::ago((string)$row["created_at"])) ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>

  <div v-show="tab === 'suspended'">
    <table class="table table-sm align-middle">
      <tbody>
      <?php foreach ($queue["suspended"] as $row) { ?>
        <tr>
          <td><a href="<?= htmlattr(url("users/" . (int)$row["id"] . "/" . $row["slug"])) ?>"><?= html($row["username"]) ?></a></td>
          <td class="small text-secondary"><?= html((string)$row["suspended_reason"]) ?></td>
          <td class="small"><?= html($row["suspended_until"] === null ? __("indefinitely") : i18n::date((string)$row["suspended_until"], "Y-m-d")) ?></td>
          <td class="text-end"><button class="btn btn-sm btn-outline-secondary" @click="unsuspend(<?= (int)$row["id"] ?>)"><?= html(__("Lift")) ?></button></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() { return { tab: "flags", handled: [], busy: false }; },
  methods: {
    async handleFlag(id, status) {
      try {
        await askbot.api("flag.handle", { id, status });
        this.handled.push(id);
      } catch (e) { askbot.toast(e.message, "danger"); }
    },
    async unsuspend(id) {
      try {
        await askbot.api("admin.unsuspenduser", { id });
        location.reload();
      } catch (e) { askbot.toast(e.message, "danger"); }
    }
  }
}).mount("#modApp");
</script>
<?php PageEngine::html("footer"); ?>
