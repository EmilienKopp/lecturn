CREATE OR REPLACE VIEW presentations_view AS

SELECT
    id,
    team_id,
    name,
    content,
    talk_settings,
    embed_token,
    created_at,
    updated_at
FROM presentations;
