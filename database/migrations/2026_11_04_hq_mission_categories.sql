-- Independent category list, maintained directly in MySQL.
CREATE TABLE IF NOT EXISTS hq_mission_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY hq_mission_categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DATA
INSERT INTO hq_mission_categories (name) VALUES
('Reconocimiento e inteligencia'),
('Extracciones y evacuaciones'),
('Perímetro y aseguramiento'),
('Búsqueda y recuperación de objetos'),
('Explosivos simulados y sabotaje'),
('Comunicaciones y tecnología'),
('Balizamiento y zonas de exclusión'),
('Defensa y protección de objetivos'),
('Ataque a posiciones'),
('Recuperación de banderas'),
('Captura e interrogatorio de VIP'),
('Rehenes y negociación'),
('Rescate de aliados'),
('Escoltas'),
('Emboscadas e intercepciones'),
('Recogida de muestras'),
('Captura de vehículos y conductores')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO hq_mission_categories (name)
SELECT DISTINCT category FROM hq_mission_catalog WHERE category <> ''
ON DUPLICATE KEY UPDATE name = VALUES(name);

UPDATE hq_mission_catalog SET category = 'Reconocimiento e inteligencia' WHERE content_hash = 'adce234abf271419edc476a8e4b100cbdb28d443e6c7bfd1bdb97edec85b3c84';
UPDATE hq_mission_catalog SET category = 'Reconocimiento e inteligencia' WHERE content_hash = '85504d73701eb31c10217813c7b9450610b06b726698f831701b945a61cd0062';
UPDATE hq_mission_catalog SET category = 'Extracciones y evacuaciones' WHERE content_hash = '4ed4b034415c7e8b7385c79e715ff3dbe2639fd38a3d2f2b600e293656ef8b02';
UPDATE hq_mission_catalog SET category = 'Extracciones y evacuaciones' WHERE content_hash = '5b72019288734d8c0874da3422e4528042fc9c61f7f3773786456238a5f2b106';
UPDATE hq_mission_catalog SET category = 'Perímetro y aseguramiento' WHERE content_hash = '7cfb9b7b97c38157e3cbfcbda7122a1a39f355c6222f473f8d598f7bc6b7a911';
UPDATE hq_mission_catalog SET category = 'Perímetro y aseguramiento' WHERE content_hash = 'a0bc828ca03d77bd94d6ac8975ee648bccb60f15756fb2578668e16c90a8b138';
UPDATE hq_mission_catalog SET category = 'Perímetro y aseguramiento' WHERE content_hash = '5c6bcbf3f757d8e262eac0bba8ad150ce63717e5079a50e912acfc7684b33931';
UPDATE hq_mission_catalog SET category = 'Perímetro y aseguramiento' WHERE content_hash = '792395970a9583833172f699bfe7a5917796d50288a410ffbc9d8a235a9992ee';
UPDATE hq_mission_catalog SET category = 'Perímetro y aseguramiento' WHERE content_hash = 'ca134e62f0178340501f110e275d3c648bbe4f10676a2371e2752ea9319032fb';
UPDATE hq_mission_catalog SET category = 'Perímetro y aseguramiento' WHERE content_hash = 'a5d261694e112a8922cd88f3c1aadbfedd3a520e43aee14553ae95913287577f';
UPDATE hq_mission_catalog SET category = 'Búsqueda y recuperación de objetos' WHERE content_hash = 'be394ce81e0ab6a3a8457ba6ae6a7299925ccb3327ae8090684c7afcb3cfa4bf';
UPDATE hq_mission_catalog SET category = 'Búsqueda y recuperación de objetos' WHERE content_hash = '704f37071e17aa581694c9c69bc6f658d201bc6216fce344a029c4d307fd49b6';
UPDATE hq_mission_catalog SET category = 'Búsqueda y recuperación de objetos' WHERE content_hash = '06e7f5d2cf329bf16a44f4a69f2cb82c0e0d01b61a4a09bc745319081c63c4af';
UPDATE hq_mission_catalog SET category = 'Explosivos simulados y sabotaje' WHERE content_hash = '6f7c86b82ae10a9ffb5ae43e634c41b2e30ccedde197728710d32f48ec9864ae';
UPDATE hq_mission_catalog SET category = 'Explosivos simulados y sabotaje' WHERE content_hash = '07c127163f5fe07c594ba17b20a467e5cb6b3ba4822763f48089cf32da5801d1';
UPDATE hq_mission_catalog SET category = 'Explosivos simulados y sabotaje' WHERE content_hash = '6062fb9dc2ceddf3cf217f64bf6df814227cb9f8e841e01c2f5319028dea4084';
UPDATE hq_mission_catalog SET category = 'Explosivos simulados y sabotaje' WHERE content_hash = 'e9c65f0e1318a77d1e91aa72bd5f164267281c8c15994ac23c0f44ef007937bf';
UPDATE hq_mission_catalog SET category = 'Explosivos simulados y sabotaje' WHERE content_hash = 'a3d9bf96974a156d1211151370bf78081309c64ccfc532300ae6b77ffe60dc91';
UPDATE hq_mission_catalog SET category = 'Comunicaciones y tecnología' WHERE content_hash = 'f16016917f4cb08f39ba907ef006c947cdcf0b3fb09850a1611f14f783c634fb';
UPDATE hq_mission_catalog SET category = 'Comunicaciones y tecnología' WHERE content_hash = '59f41bb3a07e7fc263a9fbe62bf83b857ce821d40330265372b0ee49d8051328';
UPDATE hq_mission_catalog SET category = 'Comunicaciones y tecnología' WHERE content_hash = '525b98941ce4eec9470c47a2f947a091650c1f91de8ae546cd68af03b51d8ce0';
UPDATE hq_mission_catalog SET category = 'Comunicaciones y tecnología' WHERE content_hash = '2c4f000ff37186714c178024fc2b256e1bfc52250482d61dda0f17d93b928e26';
UPDATE hq_mission_catalog SET category = 'Comunicaciones y tecnología' WHERE content_hash = 'a81a22efb5fafe93f84458a5b851725b0392d5d06eb95c0edf589e06e1bcc6e1';
UPDATE hq_mission_catalog SET category = 'Comunicaciones y tecnología' WHERE content_hash = 'eac7e2164b93606a6b6c5489220ab669fe34f2fe2f23ee2b04d686fcd877c1f2';
UPDATE hq_mission_catalog SET category = 'Balizamiento y zonas de exclusión' WHERE content_hash = 'ed0a68e3e524e9b5b29d2c96f317b387efe78f2d7775871b30a2b0c918c3c2a8';
UPDATE hq_mission_catalog SET category = 'Defensa y protección de objetivos' WHERE content_hash = '60a5f876e5db7d58d94853fe2228f01a08d27a9e9d124a9b5dda9040a56aba06';
UPDATE hq_mission_catalog SET category = 'Defensa y protección de objetivos' WHERE content_hash = '7b60460a01dd563768a3c32863aa46fb214d5ac6f381f64e78177d6b520803a5';
UPDATE hq_mission_catalog SET category = 'Ataque a posiciones' WHERE content_hash = 'fa081442d889ae9d3a9de2344d44f376fee22ee96a6834c478d0f51da7c653fa';
UPDATE hq_mission_catalog SET category = 'Recuperación de banderas' WHERE content_hash = '70318c8ada569d1b733a192fc279e81d185f1628b6671072d236374788494acc';
UPDATE hq_mission_catalog SET category = 'Captura e interrogatorio de VIP' WHERE content_hash = '8bba7db464cf5eb311011458d37a3440360589578eed2be8c04661c5a7e2b902';
UPDATE hq_mission_catalog SET category = 'Captura e interrogatorio de VIP' WHERE content_hash = 'a96fccbabcec8b6e28f3024d3ffb21fd75de6d10fe799291836aebcc32d6cbf0';
UPDATE hq_mission_catalog SET category = 'Rehenes y negociación' WHERE content_hash = 'd78bb7baf29d977d42bec03cb1e1af4a3d42bc40a34cc0ce8aa1569ec60f3939';
UPDATE hq_mission_catalog SET category = 'Rehenes y negociación' WHERE content_hash = '3e09d28c575e0d405fb5d972b0ba547280d7ebc2ccc44ddfcf9a30009120e9a0';
UPDATE hq_mission_catalog SET category = 'Rescate de aliados' WHERE content_hash = '79751860781e68f73b41dd7a70c29eaecf448f9ca31080fee6e695b4d6c08d3e';
UPDATE hq_mission_catalog SET category = 'Rescate de aliados' WHERE content_hash = '06f8c25671711c370b0bd4bbe0419fa53aa8d593da9f6f3909ba35e28c524eca';
UPDATE hq_mission_catalog SET category = 'Escoltas' WHERE content_hash = '82b4d633bc08f35a350d16eebc0c2ceb2cb7a71fe63f94bbe745330b42283318';
UPDATE hq_mission_catalog SET category = 'Escoltas' WHERE content_hash = '0bd28e2925f88390bdaf1ab885afccf274b3ca9abd42e923535c4161cb376c37';
UPDATE hq_mission_catalog SET category = 'Emboscadas e intercepciones' WHERE content_hash = 'd850b9c9600f3ec8489231985780284c969c8a88096213db8705f54c000bdfa1';
UPDATE hq_mission_catalog SET category = 'Emboscadas e intercepciones' WHERE content_hash = '58cc963759f54fd7f044e5a3319d88985b4e8fcd6d5003a4b0d1d62e4b0ff1cc';
UPDATE hq_mission_catalog SET category = 'Emboscadas e intercepciones' WHERE content_hash = 'c350340644a16c403a2962b37ddeef0cc56dbdddca12d37eda19fb8b598e3731';
UPDATE hq_mission_catalog SET category = 'Emboscadas e intercepciones' WHERE content_hash = 'e3ef2155ccd36f49f21a604213264481ef92f7957e2cf28fdf9ee122a41e37ce';
UPDATE hq_mission_catalog SET category = 'Emboscadas e intercepciones' WHERE content_hash = '050f552e586bfb6de350164d5356295ddcfadc1240731fc189790070dfe4d333';
UPDATE hq_mission_catalog SET category = 'Recogida de muestras' WHERE content_hash = '28649abb9fa33718f2d8352a0f2f66f5c162e85f9bb0be4ccd49ccf560de8de2';
UPDATE hq_mission_catalog SET category = 'Recogida de muestras' WHERE content_hash = '87eaa9302ab09e3512e062ddef92161c08dc55ee819a4310e22ed301917fa6e5';
UPDATE hq_mission_catalog SET category = 'Captura de vehículos y conductores' WHERE content_hash = 'c572db5bf7476d78821894b67fcb763b54e14be71731dd7d314b078f384ade04';
