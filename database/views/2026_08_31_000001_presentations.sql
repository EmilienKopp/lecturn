CREATE VIEW presentations_view AS

SELECT
    id,
    team_id,
    name,
    content,
    talk_settings,
    flow,
    embed_token,
    yoyotranslate_session_id,
    yoyotranslate_session_started_at,
    created_at,
    updated_at
FROM presentations;
