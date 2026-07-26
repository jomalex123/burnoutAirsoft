SET @instagram_cache_items_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'instagram_cache_items'
);

SET @alter_instagram_image_data := (
  SELECT IF(
    @instagram_cache_items_exists > 0 AND COUNT(*) > 0 AND MAX(DATA_TYPE) <> 'longblob',
    'ALTER TABLE instagram_cache_items MODIFY image_data LONGBLOB NOT NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'instagram_cache_items'
    AND COLUMN_NAME = 'image_data'
);
PREPARE statement FROM @alter_instagram_image_data;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @gallery_event_images_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'gallery_event_images'
);

SET @alter_gallery_image_data := (
  SELECT IF(
    @gallery_event_images_exists > 0 AND COUNT(*) > 0 AND MAX(DATA_TYPE) <> 'longblob',
    'ALTER TABLE gallery_event_images MODIFY image_data LONGBLOB NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'gallery_event_images'
    AND COLUMN_NAME = 'image_data'
);
PREPARE statement FROM @alter_gallery_image_data;
EXECUTE statement;
DEALLOCATE PREPARE statement;
