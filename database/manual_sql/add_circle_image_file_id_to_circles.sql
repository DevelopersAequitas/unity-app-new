-- Add circle_image_file_id column to circles table
ALTER TABLE circles ADD COLUMN IF NOT EXISTS circle_image_file_id UUID NULL;
