CREATE VIEW presentations_view AS

SELECT
    id,
    team_id,
    name,
    content,
    created_at,
    updated_at
FROM presentations;
