CREATE VIEW presentations_view AS

SELECT
    id,
    team_id,
    name,
    content,
    embed_token,
    created_at,
    updated_at
FROM presentations;
