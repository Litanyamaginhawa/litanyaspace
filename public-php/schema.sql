-- ============================================================
-- LitanyaSpace PHP/MySQL LAYER  (small, focused)
-- Supabase already handles posts/messages/notifications/etc.
-- This MySQL layer stores only what Supabase cannot:
--   * trending_cache     - precomputed hashtags (cron-refreshed)
--   * feed_stats         - daily DAU/posts/messages counters
--   * audio_library      - user-favorites/pinned tracks from Jamendo
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS trending_cache (
  tag           VARCHAR(64) NOT NULL,
  count_24h     INT         NOT NULL DEFAULT 0,
  updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tag),
  KEY idx_trending_count (count_24h DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feed_stats (
  day           DATE        NOT NULL,
  active_users  INT         NOT NULL DEFAULT 0,
  new_posts     INT         NOT NULL DEFAULT 0,
  new_messages  INT         NOT NULL DEFAULT 0,
  updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cached/pinned Jamendo tracks that the user liked. When a user
-- attaches an audio track to a post, the frontend saves this row
-- (track_id, preview mp3 url, artist, license) so playback does not
-- hit Jamendo's rate limit on every page view.
CREATE TABLE IF NOT EXISTS audio_library (
  id            CHAR(36)     NOT NULL,
  provider      VARCHAR(32)  NOT NULL DEFAULT 'jamendo',
  external_id   VARCHAR(64)  DEFAULT NULL,   -- Jamendo track id
  title         VARCHAR(200) NOT NULL,
  artist        VARCHAR(120) DEFAULT NULL,
  url           VARCHAR(1024) NOT NULL,      -- preview mp3 (https)
  duration_ms   INT          DEFAULT NULL,
  license       VARCHAR(64)  DEFAULT 'CC',
  attribution   VARCHAR(500) DEFAULT NULL,
  added_by      CHAR(36)     DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audio_provider (provider, external_id),
  KEY idx_audio_user (added_by, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
