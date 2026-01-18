-- Drop existing tables if they exist
DROP TABLE IF EXISTS task_assignments CASCADE;
DROP TABLE IF EXISTS tasks CASCADE;
DROP TABLE IF EXISTS user_profiles CASCADE;
DROP TABLE IF EXISTS sessions CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS priorities CASCADE;
DROP TABLE IF EXISTS statuses CASCADE;

-- Create enum-like tables for priorities and statuses
CREATE TABLE priorities (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    level INTEGER UNIQUE NOT NULL
);

CREATE TABLE statuses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

-- Insert priority levels
INSERT INTO priorities (name, level) VALUES 
    ('Low', 1),
    ('Medium', 2),
    ('High', 3),
    ('Critical', 4);

-- Insert status types
INSERT INTO statuses (name) VALUES 
    ('Not Started'),
    ('In Progress'),
    ('Complete');

-- Users table (updated with role)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

-- User profiles table (ONE-TO-ONE relationship with users)
CREATE TABLE user_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    bio TEXT,
    phone VARCHAR(20),
    avatar_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sessions table (ONE-TO-MANY: one user can have multiple sessions)
CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Tasks table (MANY-TO-ONE: many tasks belong to one creator)
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    task_name VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATE,
    priority_id INTEGER REFERENCES priorities(id) ON DELETE SET NULL,
    status_id INTEGER NOT NULL REFERENCES statuses(id) DEFAULT 1,
    created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Task assignments table (MANY-TO-MANY: tasks can be assigned to multiple users)
CREATE TABLE task_assignments (
    id SERIAL PRIMARY KEY,
    task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    UNIQUE(task_id, user_id)
);

-- Create indexes for better performance
CREATE INDEX idx_tasks_created_by ON tasks(created_by);
CREATE INDEX idx_tasks_status ON tasks(status_id);
CREATE INDEX idx_tasks_due_date ON tasks(due_date);
CREATE INDEX idx_task_assignments_user ON task_assignments(user_id);
CREATE INDEX idx_task_assignments_task ON task_assignments(task_id);
CREATE INDEX idx_sessions_token ON sessions(token);
CREATE INDEX idx_sessions_user ON sessions(user_id);

-- VIEW 1: User tasks overview with all details
CREATE OR REPLACE VIEW v_user_tasks_overview AS
SELECT 
    u.id as user_id,
    u.firstname || ' ' || u.lastname as user_name,
    t.id as task_id,
    t.task_name,
    t.description,
    t.due_date,
    p.name as priority,
    p.level as priority_level,
    s.name as status,
    t.created_at,
    ta.assigned_at,
    ta.completed_at,
    CASE 
        WHEN t.due_date < CURRENT_DATE AND s.name != 'Complete' THEN 'Overdue'
        WHEN t.due_date = CURRENT_DATE AND s.name != 'Complete' THEN 'Due Today'
        ELSE 'On Track'
    END as task_urgency
FROM users u
INNER JOIN task_assignments ta ON u.id = ta.user_id
INNER JOIN tasks t ON ta.task_id = t.id
LEFT JOIN priorities p ON t.priority_id = p.id
INNER JOIN statuses s ON t.status_id = s.id
WHERE u.enabled = TRUE;

-- VIEW 2: Admin dashboard statistics
CREATE OR REPLACE VIEW v_admin_dashboard AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE enabled = TRUE) as total_active_users,
    (SELECT COUNT(*) FROM users WHERE enabled = FALSE) as total_disabled_users,
    (SELECT COUNT(*) FROM tasks) as total_tasks,
    (SELECT COUNT(*) FROM tasks t 
     INNER JOIN statuses s ON t.status_id = s.id 
     WHERE s.name = 'Complete') as completed_tasks,
    (SELECT COUNT(*) FROM tasks t 
     INNER JOIN statuses s ON t.status_id = s.id 
     WHERE s.name = 'In Progress') as in_progress_tasks,
    (SELECT COUNT(*) FROM tasks 
     WHERE due_date < CURRENT_DATE) as overdue_tasks,
    (SELECT COUNT(*) FROM sessions 
     WHERE is_active = TRUE AND expires_at > CURRENT_TIMESTAMP) as active_sessions;

-- TRIGGER 1: Update tasks.updated_at on any task modification
CREATE OR REPLACE FUNCTION update_task_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_task_timestamp
BEFORE UPDATE ON tasks
FOR EACH ROW
EXECUTE FUNCTION update_task_timestamp();

-- TRIGGER 2: Auto-complete task assignment when task status is set to Complete
CREATE OR REPLACE FUNCTION auto_complete_assignment()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status_id = (SELECT id FROM statuses WHERE name = 'Complete') 
       AND OLD.status_id != NEW.status_id THEN
        UPDATE task_assignments
        SET completed_at = CURRENT_TIMESTAMP
        WHERE task_id = NEW.id AND completed_at IS NULL;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_auto_complete_assignment
AFTER UPDATE ON tasks
FOR EACH ROW
EXECUTE FUNCTION auto_complete_assignment();

-- FUNCTION 1: Get user's task statistics
CREATE OR REPLACE FUNCTION get_user_task_stats(p_user_id INTEGER)
RETURNS TABLE (
    total_tasks BIGINT,
    completed_tasks BIGINT,
    in_progress_tasks BIGINT,
    not_started_tasks BIGINT,
    overdue_tasks BIGINT,
    completion_rate NUMERIC
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        COUNT(*) as total_tasks,
        COUNT(*) FILTER (WHERE s.name = 'Complete') as completed_tasks,
        COUNT(*) FILTER (WHERE s.name = 'In Progress') as in_progress_tasks,
        COUNT(*) FILTER (WHERE s.name = 'Not Started') as not_started_tasks,
        COUNT(*) FILTER (WHERE t.due_date < CURRENT_DATE AND s.name != 'Complete') as overdue_tasks,
        CASE 
            WHEN COUNT(*) > 0 THEN 
                ROUND((COUNT(*) FILTER (WHERE s.name = 'Complete')::NUMERIC / COUNT(*)::NUMERIC) * 100, 2)
            ELSE 0
        END as completion_rate
    FROM task_assignments ta
    INNER JOIN tasks t ON ta.task_id = t.id
    INNER JOIN statuses s ON t.status_id = s.id
    WHERE ta.user_id = p_user_id;
END;
$$ LANGUAGE plpgsql;

-- FUNCTION 2: Safe delete user (only if not logged in)
CREATE OR REPLACE FUNCTION safe_delete_user(p_user_id INTEGER, p_admin_id INTEGER)
RETURNS TEXT AS $$
DECLARE
    v_active_sessions INTEGER;
    v_is_admin BOOLEAN;
BEGIN
    -- Check if requester is admin
    SELECT role = 'admin' INTO v_is_admin
    FROM users WHERE id = p_admin_id;
    
    IF NOT v_is_admin THEN
        RETURN 'ERROR: Only admins can delete users';
    END IF;
    
    -- Check for active sessions
    SELECT COUNT(*) INTO v_active_sessions
    FROM sessions
    WHERE user_id = p_user_id 
      AND is_active = TRUE 
      AND expires_at > CURRENT_TIMESTAMP;
    
    IF v_active_sessions > 0 THEN
        RETURN 'ERROR: User has active sessions. Cannot delete.';
    END IF;
    
    -- Delete user (CASCADE will handle related records)
    DELETE FROM users WHERE id = p_user_id;
    
    RETURN 'SUCCESS: User deleted successfully';
END;
$$ LANGUAGE plpgsql;

-- Insert sample admin and users
INSERT INTO users (firstname, lastname, email, password, role, enabled)
VALUES 
    ('Admin', 'User', 'admin@example.com', '$2y$10$YourHashedPasswordHere', 'admin', TRUE),
    ('Jan', 'Kowalski', 'jan.kowalski@example.com', '$2y$10$ZbzQrqD1vDhLJpYe/vzSbeDJHTUnVPCpwlXclkiFa8dO5gOAfg8tq', 'user', TRUE),
    ('Anna', 'Nowak', 'anna.nowak@example.com', '$2y$10$ZbzQrqD1vDhLJpYe/vzSbeDJHTUnVPCpwlXclkiFa8dO5gOAfg8tq', 'user', TRUE);

-- Insert user profiles
INSERT INTO user_profiles (user_id, bio) VALUES
    (1, 'System Administrator'),
    (2, 'Lubi programować w JS i PL/SQL.'),
    (3, 'Frontend Developer');

-- Insert sample tasks
INSERT INTO tasks (task_name, description, due_date, priority_id, status_id, created_by)
VALUES 
    ('Finish project documentation', 'Complete all technical documentation', CURRENT_DATE + INTERVAL '7 days', 3, 2, 1),
    ('Code review', 'Review pull requests', CURRENT_DATE + INTERVAL '2 days', 2, 1, 1),
    ('Team meeting', 'Weekly sync meeting', CURRENT_DATE + INTERVAL '1 day', 1, 1, 2);

-- Assign tasks to users
INSERT INTO task_assignments (task_id, user_id) VALUES
    (1, 2),
    (1, 3),
    (2, 2),
    (3, 2),
    (3, 3);