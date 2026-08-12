-- ---------------------------------------------------------------------------
-- askbot_php - base data (badge catalogue + default settings).
-- Safe to re-run: every statement is an idempotent upsert.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

INSERT INTO `schema_migrations` (`version`) VALUES ('2026_08_12_000001_initial')
  ON DUPLICATE KEY UPDATE `version` = `version`;

-- Badges ---------------------------------------------------------------------
INSERT INTO `badges` (`key_name`,`name`,`description`,`level`,`is_multiple`) VALUES
  ('autobiographer','Autobiographer','Completed the "about me" section of your profile','bronze',0),
  ('first_question','Curious','Asked your first question','bronze',0),
  ('first_answer','Contributor','Posted your first answer','bronze',0),
  ('first_comment','Commentator','Left your first comment','bronze',0),
  ('first_vote','Supporter','Cast your first vote','bronze',0),
  ('critic','Critic','Cast your first down vote','bronze',0),
  ('editor','Editor','Edited a post for the first time','bronze',0),
  ('organizer','Organizer','Created or edited a tag wiki','bronze',0),
  ('scholar','Scholar','Accepted an answer to your own question','bronze',0),
  ('teacher','Teacher','Answer was upvoted at least once','bronze',1),
  ('student','Student','Question was upvoted at least once','bronze',1),
  ('nice_question','Nice Question','Question score of 5 or more','bronze',1),
  ('nice_answer','Nice Answer','Answer score of 5 or more','bronze',1),
  ('good_question','Good Question','Question score of 15 or more','silver',1),
  ('good_answer','Good Answer','Answer score of 15 or more','silver',1),
  ('great_question','Great Question','Question score of 40 or more','gold',1),
  ('great_answer','Great Answer','Answer score of 40 or more','gold',1),
  ('popular_question','Popular Question','Question viewed 250 times','bronze',1),
  ('notable_question','Notable Question','Question viewed 1000 times','silver',1),
  ('famous_question','Famous Question','Question viewed 5000 times','gold',1),
  ('self_learner','Self-Learner','Answered your own question with 3 or more upvotes','bronze',1),
  ('enlightened','Enlightened','Accepted answer with 10 or more upvotes','silver',1),
  ('guru','Guru','Accepted answer with 30 or more upvotes','gold',1),
  ('cleanup','Cleanup','Removed a post with a negative score','bronze',0),
  ('citizen_patrol','Citizen Patrol','Flagged a post that a moderator accepted','bronze',0),
  ('civic_duty','Civic Duty','Cast 100 votes','silver',0),
  ('pundit','Pundit','Left 10 comments with a score of 5 or more','silver',0),
  ('taxonomist','Taxonomist','Created a tag used by 25 questions','silver',0),
  ('enthusiast','Enthusiast','Visited the site on 30 consecutive days','silver',0),
  ('fanatic','Fanatic','Visited the site on 100 consecutive days','gold',0),
  ('necromancer','Necromancer','Answered a question 60 days later with 5 or more upvotes','silver',1),
  ('populist','Populist','Highest scoring answer that outscored an accepted answer','gold',1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`), `level`=VALUES(`level`);

-- Default settings ------------------------------------------------------------
INSERT INTO `config` (`key_name`,`value_text`) VALUES
  ('site_title','Askbot'),
  ('site_tagline','Ask questions. Get answers. Share knowledge.'),
  ('site_description','A community driven question and answer site.'),
  ('site_language','en'),
  ('site_theme','auto'),
  ('questions_per_page','30'),
  ('answers_per_page','30'),
  ('allow_anonymous_read','1'),
  ('registration_open','1'),
  ('require_email_verification','1'),
  ('min_title_length','15'),
  ('min_question_length','20'),
  ('min_answer_length','20'),
  ('max_tags_per_question','5'),
  ('min_tags_per_question','1'),
  ('karma_new_user','1'),
  ('karma_question_upvote','5'),
  ('karma_question_downvote','-2'),
  ('karma_answer_upvote','10'),
  ('karma_answer_downvote','-2'),
  ('karma_downvote_cost','-1'),
  ('karma_answer_accepted','15'),
  ('karma_accept_answer','2'),
  ('karma_daily_cap','200'),
  ('threshold_comment','1'),
  ('threshold_vote_up','15'),
  ('threshold_vote_down','125'),
  ('threshold_flag','15'),
  ('threshold_edit_wiki','100'),
  ('threshold_close_vote','500'),
  ('threshold_edit_others','2000'),
  ('threshold_tag_wiki','1500'),
  ('threshold_delete_vote','3000'),
  ('close_votes_needed','3'),
  ('flags_needed_autohide','5'),
  ('feed_item_count','30')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;
