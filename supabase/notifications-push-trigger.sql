-- ============================================================
-- Litanya Safespace — closed-app push trigger
-- Runs in the Supabase dashboard:  SQL Editor  ->  New query  ->  Run
--
-- What it does: whenever a row is added to `notifications`
-- (message, comment, reaction, myday_reply), it calls the
-- `send-notification-push` Edge Function, which delivers a real
-- VAPID push to every device that user enabled notifications on —
-- even when the PWA is fully closed.
--
-- ⚠️ BEFORE running, replace the two values below:
--   1. REPLACE_WITH_YOUR_LONG_RANDOM_SECRET  -> same string you set as
--      the PUSH_TRIGGER_SECRET Function Secret.
--   2. Confirm the function URL uses your project ref:
--      https://gmjtpvxwymevjsicbwlh.supabase.co/functions/v1/send-notification-push
-- ============================================================

-- pg_net lets Postgres make HTTP calls (already enabled on Supabase).
create extension if not exists pg_net with schema extensions;

create or replace function public.litanya_push_notify()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
  payload jsonb;
begin
  payload := jsonb_build_object(
    'record', jsonb_build_object(
      'user_id', new.user_id,
      'type',    new.type,
      'title',   new.title,
      'body',    new.body
    )
  );

  perform net.http_post(
    url     := 'https://gmjtpvxwymevjsicbwlh.supabase.co/functions/v1/send-notification-push',
    headers := jsonb_build_object(
      'Content-Type',  'application/json',
      'x-push-secret', 'REPLACE_WITH_YOUR_LONG_RANDOM_SECRET'
    ),
    body    := payload
  );

  return new;
end $$;

drop trigger if exists litanya_push_notify on public.notifications;
create trigger litanya_push_notify
  after insert on public.notifications
  for each row execute function public.litanya_push_notify();

-- Done. Test by having a second account send you a message while
-- the app is closed on your phone.
