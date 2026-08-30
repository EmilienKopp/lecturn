CREATE VIEW presentations_view AS

SELECT
    id,
    team_id,
    name,
    content,
    talk_settings,
    flow,
    embed_token,
    created_at,
    updated_at
FROM presentations;
