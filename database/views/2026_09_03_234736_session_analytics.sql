CREATE VIEW
    session_analytics AS
SELECT
    s.id,
    s.presentation_id,
    s.team_id,
    p.name AS presentation_name,
    s.started_at,
    s.ended_at,
    s.last_seen_at,
    s.reaction_counts,
    s.reaction_total,
    s.viewer_count,
    s.created_at,
    s.updated_at
FROM
    presentation_sessions s
    JOIN presentations p ON p.id = s.presentation_id;
