CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) DEFAULT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admin_users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  success TINYINT(1) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY admin_login_audit_username_index (username),
  KEY admin_login_audit_created_at_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope VARCHAR(80) NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  blocked_until DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY rate_limits_scope_identifier_unique (scope, identifier_hash),
  KEY rate_limits_blocked_until_index (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_date DATE NOT NULL,
  title VARCHAR(180) NOT NULL,
  time_slot ENUM('M', 'T', 'N') NOT NULL,
  max_attendees INT UNSIGNED NOT NULL DEFAULT 40,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY events_event_date_index (event_date),
  KEY events_time_slot_index (time_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  team_name VARCHAR(120) DEFAULT NULL,
  accepted_rules TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY registrations_event_id_index (event_id),
  KEY registrations_email_index (email),
  CONSTRAINT registrations_event_id_foreign
    FOREIGN KEY (event_id) REFERENCES events (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_attendees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  document VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY registration_attendees_registration_id_index (registration_id),
  CONSTRAINT registration_attendees_registration_id_foreign
    FOREIGN KEY (registration_id) REFERENCES registrations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_email_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id BIGINT UNSIGNED NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY registration_email_notifications_registration_id_index (registration_id),
  KEY registration_email_notifications_status_index (status),
  CONSTRAINT registration_email_notifications_registration_id_foreign
    FOREIGN KEY (registration_id) REFERENCES registrations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  src TEXT NOT NULL,
  alt VARCHAR(190) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY gallery_events_slug_unique (slug),
  UNIQUE KEY gallery_events_event_id_unique (event_id),
  KEY gallery_events_visible_index (is_visible),
  CONSTRAINT gallery_events_event_id_foreign
    FOREIGN KEY (event_id) REFERENCES events (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_event_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gallery_event_id BIGINT UNSIGNED NOT NULL,
  src TEXT NULL,
  original_name VARCHAR(255) DEFAULT NULL,
  image_mime VARCHAR(80) DEFAULT NULL,
  image_data LONGBLOB NULL,
  image_size INT UNSIGNED NOT NULL DEFAULT 0,
  image_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY gallery_event_images_event_index (gallery_event_id, id),
  CONSTRAINT gallery_event_images_event_id_foreign
    FOREIGN KEY (gallery_event_id) REFERENCES gallery_events (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instagram_cache_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instagram_id VARCHAR(80) NOT NULL,
  caption TEXT DEFAULT NULL,
  media_type VARCHAR(40) DEFAULT NULL,
  permalink VARCHAR(500) NOT NULL,
  published_at DATETIME DEFAULT NULL,
  image_mime VARCHAR(80) NOT NULL DEFAULT 'image/jpeg',
  image_data LONGBLOB NOT NULL,
  image_size INT UNSIGNED NOT NULL DEFAULT 0,
  image_hash CHAR(64) DEFAULT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY instagram_cache_items_instagram_id_unique (instagram_id),
  KEY instagram_cache_items_visible_published_index (is_visible, published_at),
  KEY instagram_cache_items_fetched_at_index (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instagram_cache_state (
  state_key VARCHAR(80) NOT NULL,
  state_value MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una operación HQ por evento del calendario.
CREATE TABLE IF NOT EXISTS hq_operations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(180) NOT NULL,
  operation_date DATE NOT NULL,
  status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY hq_operations_event_unique (event_id),
  CONSTRAINT hq_operations_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_access (
  operation_id INT UNSIGNED NOT NULL,
  team ENUM('alpha', 'bravo') NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  PRIMARY KEY (operation_id, team),
  CONSTRAINT hq_access_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_mission_catalog (
  category VARCHAR(80) NOT NULL DEFAULT '',
  mission_type VARCHAR(80) NOT NULL DEFAULT '',
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  briefing TEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY hq_mission_catalog_content_unique (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial catalogue from misiones.md. Preserve variants and existing edits.
INSERT INTO hq_mission_catalog (title, briefing, content_hash) VALUES
('Misión de perímetro en Frontera — objeto: cinta de balizar', 'Buscar un objetivo de interés y llamar a hq para avisar que han encontrado dicho objetivo. Hacer el perímetro hasta que HQ diga algo. Si lo han encontrado rápido avisar al otro enlace para que le meta caña y haya conflicto.', '7cfb9b7b97c38157e3cbfcbda7122a1a39f355c6222f473f8d598f7bc6b7a911'),
('Misión de perímetro en Frontera — objeto: detector', 'Buscar un objetivo de interés y llamar a hq para avisar que han encontrado dicho objetivo. Hacer el perímetro hasta que HQ diga algo. Si lo han encontrado rápido avisar al otro enlace para que le meta caña y haya conflicto.', 'a0bc828ca03d77bd94d6ac8975ee648bccb60f15756fb2578668e16c90a8b138'),
('Balizar zona de exclusión', 'Una vez conseguida la baliza, deberá contactar con HQ y dirigirse hasta  la zona de la Killhouse donde deberán balizar la futura edificación con la cinta haciendo perímetro durante X minutos', 'ed0a68e3e524e9b5b29d2c96f317b387efe78f2d7775871b30a2b0c918c3c2a8'),
('Detector de metales', 'Usar artilugio para encontrar la ubicación exacta de la caja de munición. Dentro más intel de la zona de exclusión. El objeto se encontrará en la Cabaña de piedra del bosque (¿?)', 'be394ce81e0ab6a3a8457ba6ae6a7299925ccb3327ae8090684c7afcb3cfa4bf'),
('Inhibidor', 'Deberán colocarlo en la zona de cabaña de roca CQB haciendo perímetro. Una vez instalado, activar la cuenta atrás de 4 minutos.', 'f16016917f4cb08f39ba907ef006c947cdcf0b3fb09850a1611f14f783c634fb'),
('Dispositivo IED — instalación en Killhouse', 'El especialista deberá montar el dispositivo IED en las coordenadas indicadas en la intel conseguida en la misión anterior. (Killhouse)', '6f7c86b82ae10a9ffb5ae43e634c41b2e30ccedde197728710d32f48ec9864ae'),
('Punto de interés en zona destruida', 'Gracias a la misión del inhibidor hemos conseguido hackear un dron enemigo y creemos que ha caído cerca de AT. Vemos mucho movimiento en esa dirección. Hay que apresurarse para incautar toda la información que contenga.', '704f37071e17aa581694c9c69bc6f658d201bc6216fce344a029c4d307fd49b6'),
('Dron caído', 'Los enemigos han hackeado nuestro sistema de vigilancia. Hay que encontrar el Dron y extraerlo antes que lo encuentre el equipo enemigo. (Activar ATAKs)', '06e7f5d2cf329bf16a44f4a69f2cb82c0e0d01b61a4a09bc745319081c63c4af'),
('Emboscada — información hackeada', 'Con la info. hackeada podrán llegar antes a la zona y hacer una emboscada a la squad rival. Tendrán que hacer perímetro y abatir a todos los miembros, consiguiendo sus identificaciones (fichas poker).', 'd850b9c9600f3ec8489231985780284c969c8a88096213db8705f54c000bdfa1'),
('Bomba con temporizador — búnker hostil', 'Se ha identificado un búnker hostil donde guardan armamento nuclear. Se debe transportar explosivo y hacerlo detonar pasada la cuenta atrás. Antes de poder activar la bomba debe reposar 10 minutos. Por lo que se debe hacer perímetro antes de activarla.', '07c127163f5fe07c594ba17b20a467e5cb6b3ba4822763f48089cf32da5801d1'),
('Rescate aliado — extracción en Check Point', 'El especialista ha sido gravemente herido tras la detonación. Necesita atención sanitaria urgente y extracción en vehículo. Deben llegar hasta Check Point. Habrá que estabilizar antes de su traslado. Debe moverse entre dos jugadores. Los cuales no podrán usar sus armas. Si hay que responder al fuego deberán dejar a su compañero.', '79751860781e68f73b41dd7a70c29eaecf448f9ca31080fee6e695b4d6c08d3e'),
('Teléfono prepago — recuperar el teléfono del especialista', 'Habéis recibido un soplo y conocéis la ubicación del enemigo. Debéis conseguir el teléfono del especialista. Contiene información vital para la extracción del equipo.', '59f41bb3a07e7fc263a9fbe62bf83b857ce821d40330265372b0ee49d8051328'),
('Extracción Check Point — recuperación de combustible', 'Debéis avisar a HQ de que tenéis el teléfono para hackearlo. Según avance el otro equipo habrá cooldown o misión extra para incautar la gasolina necesaria para poder llegar a base. Buscar unos bidones rojos para poder rellenar el vehículo y poder finalizar la partida. Punto de extracción Check Point.', '4ed4b034415c7e8b7385c79e715ff3dbe2639fd38a3d2f2b600e293656ef8b02'),
('Reconocimiento — descubrir objetivos del enemigo', 'Encontrar el equipo enemigo e investigar y saber cual es el objetivo del enemigo. Intentar evitar que se lleven el objetivo el equipo contrario.', 'adce234abf271419edc476a8e4b100cbdb28d443e6c7bfd1bdb97edec85b3c84'),
('Reconocimiento — identificación de puntos del mapa', 'Identificación de objetivos en el mapa, interactuar con HQ para poner nombres a los puntos. Encontrar un PE enemigo sin identificar.', '85504d73701eb31c10217813c7b9450610b06b726698f831701b945a61cd0062'),
('Tablet — hackeo de comunicaciones desde HQ', 'Movilizar squad hasta HQ donde se les entregará un dispositivo con el que podrán hackear comunicaciones y tener una ventana de 15 min para saber todo lo que hace la otra squad. Averiguarán la misión en curso y el objeto que portan. Ubicación de su PE (Punto de encuentro).', '525b98941ce4eec9470c47a2f947a091650c1f91de8ae546cd68af03b51d8ce0'),
('Lanzapepos', 'HQ entregará un lanzagranadas con dos cargas para poder conseguir eliminar el PE enemigo y ralentizar su avance en las misiones. Teniendo que reagruparse en otro punto del mapa asignado por HQ.', 'fa081442d889ae9d3a9de2344d44f376fee22ee96a6834c478d0f51da7c653fa'),
('Bandera', 'El equipo enemigo nos ha identificado ha conseguido identificar nuestra procedencia y nos han robado lo más preciado. Nuestra bandera y emblema. Hay que recuperarla sin dejar ningún miembro en pie.', '70318c8ada569d1b733a192fc279e81d185f1628b6671072d236374788494acc'),
('Captura VIP — interrogatorio en el punto de encuentro', 'Nuestra misión es capturar un miembro (STAFF) del bando enemigo y llevarlo hasta nuestro PE para poder interrogarlo. Esto obligará al otro equipo a parar de inmediato sus actividades para recuperar dicho efectivo.', '8bba7db464cf5eb311011458d37a3440360589578eed2be8c04661c5a7e2b902'),
('Intercambio de rehenes — rescate urgente', 'Nos han capturado un miembro de nuestra unidad, debemos recuperar a nuestro compañero antes de que cause una brecha de seguridad. Habrá que dejar la misión en curso y hacer lo posible por encontrarlo. Una vez detectada la unidad enemiga habrá que decidir cómo actuar frente a la situación. Amistoso o Radical', 'd78bb7baf29d977d42bec03cb1e1af4a3d42bc40a34cc0ce8aa1569ec60f3939'),
('Escolta HQ — ocultar información comprometida', 'Tendrá que escoltar a HQ hasta una ubicación concreta donde deberá esconder información comprometida.', '82b4d633bc08f35a350d16eebc0c2ceb2cb7a71fe63f94bbe745330b42283318'),
('Protección del dispositivo y campo de minas', 'Equipo defensor (S1) debe localizar el dispositivo cerca de la cabaña. Montar el campo de minas y proteger el dispositivo en su respawn hasta que llegue la extracción.', '60a5f876e5db7d58d94853fe2228f01a08d27a9e9d124a9b5dda9040a56aba06'),
('Interceptar el robo del dispositivo', 'Equipo atacante (S2) desplegados en la zona para evitar el robo de un dispositivo de vital importancia, si acceden a las comunicaciones enemigas pirateando AT conocerán la ubicación exacta y los planes que trama el bando contrario. Si no, su mejor opción es llegar al punto de interés y reconocer el terreno paso a paso hasta descubrirlo con sus propios ojos.', '7b60460a01dd563768a3c32863aa46fb214d5ac6f381f64e78177d6b520803a5'),
('Misión de perímetro en Check Point — objeto: Bomba', 'Buscar un objetivo de interés y llamar a hq para avisar que han encontrado dicho objetivo. Hacer el perímetro hasta que HQ diga algo. Si lo han encontrado rápido avisar al otro enlace para que le meta caña y haya conflicto.', '5c6bcbf3f757d8e262eac0bba8ad150ce63717e5079a50e912acfc7684b33931'),
('Misión de perímetro en Frontera — objeto: Tablet', 'Buscar un objetivo de interés y llamar a hq para avisar que han encontrado dicho objetivo. Hacer el perímetro hasta que HQ diga algo. Si lo han encontrado rápido avisar al otro enlace para que le meta caña y haya conflicto.', '792395970a9583833172f699bfe7a5917796d50288a410ffbc9d8a235a9992ee'),
('Bomba con temporizador — accesos a galerías', 'Se ha identificado en un mapa un conjunto de accesos a galerías donde se guarda armamento nuclear. Se debe transportar explosivo y hacerlo detonar pasada la cuenta atrás. Antes de poder activar la bomba debe reposar 5 minutos. Por lo que se debe hacer perímetro antes de activarla.', '6062fb9dc2ceddf3cf217f64bf6df814227cb9f8e841e01c2f5319028dea4084'),
('Tablet — interceptación de comunicaciones en AT', 'Movilizar squad hasta AT donde deberán hacer perímetro (a determinar) y podrán utilizar el dispositivo para hackear comunicaciones y tener una ventana de 15 min para saber todo lo que hace la otra squad. Averiguarán la misión en curso y el objeto que portan. Ubicación de su PE (Punto de encuentro).', '2c4f000ff37186714c178024fc2b256e1bfc52250482d61dda0f17d93b928e26'),
('Rescate aliado — traslado al punto de encuentro', 'El especialista ha sido gravemente herido tras la última detonación. Necesita atención sanitaria urgente. Deben llegar hasta (PE) donde les espera un cirujano. Habrá que estabilizar antes de su traslado. Debe moverse entre dos jugadores. Los cuales no podrán usar sus armas. Si hay que responder al fuego deberán dejar a su compañero.', '06f8c25671711c370b0bd4bbe0419fa53aa8d593da9f6f3909ba35e28c524eca'),
('Captura VIP — recuperar información del equipo', 'Nuestra misión es capturar un miembro (STAFF) del bando enemigo y llevarlo hasta nuestro PE para poder interrogarlo. Deben sustraer toda la información que contenga. (No os olvidéis de revisar su equipo). Esto obligará al otro equipo a parar de inmediato sus actividades para recuperar dicho efectivo.', 'a96fccbabcec8b6e28f3024d3ffb21fd75de6d10fe799291836aebcc32d6cbf0'),
('Intercambio de rehenes — negociación o rescate', 'Nos han capturado un miembro de nuestra unidad, debemos recuperar a nuestro compañero antes de que cause una brecha de seguridad. Una vez detectada la unidad enemiga habrá que decidir cómo actuar frente a la situación. Amistoso o Radical.', '3e09d28c575e0d405fb5d972b0ba547280d7ebc2ccc44ddfcf9a30009120e9a0'),
('Emboscada — información del interrogatorio', 'Con la info. del interrogatorio podrán hacer una emboscada a la squad rival. Tendrán que hacer perímetro y abatir a todos los miembros, consiguiendo sus identificaciones (fichas poker).', '58cc963759f54fd7f044e5a3319d88985b4e8fcd6d5003a4b0d1d62e4b0ff1cc'),
('Escolta VIP — preparación de extracción', 'Tendrá que escoltar al VIP hasta una ubicación concreta donde deberá preparar la zona de extracción. Hacer perímetro hasta que el VIP pueda contactar con HQ y considere que pueda extraer con seguridad.', '0bd28e2925f88390bdaf1ab885afccf274b3ca9abd42e923535c4161cb376c37'),
('Dispositivo IED — montaje con material localizado', 'El especialista deberá montar el dispositivo IED con los objetos que encuentre en las coordenadas indicadas en la intel conseguida en la misión anterior.', 'e9c65f0e1318a77d1e91aa72bd5f164267281c8c15994ac23c0f44ef007937bf'),
('Extracción alternativa — localizar las llaves del vehículo', 'Debéis avisar a HQ de que tenéis un topo. Han conseguido hacer una llamada y tenéis al equipo rival en camino. (Según avance el otro equipo habrá cooldown o misión extra). Debéis buscar una alternativa… Revisadlo bien, puede que lleve unas llaves encima ¿Serán de un vehículo?', '5b72019288734d8c0874da3422e4528042fc9c61f7f3773786456238a5f2b106'),
('Teléfono prepago — rescatar al contacto', 'Rápido coged todo el material que habéis preparado y salid cagando hostias. Tenemos un soplo y conocéis la ubicación del enemigo. Debéis conseguir sacar con vida a vuestro contacto y huir en su vehículo.  Contiene información vital de vuestro entorno, no podeís fallarle o os quedaréis solos en este bosque. Usad el dispositivo para no dejar rastro y que salte todo en pedazos. (GRANADA FOGUEO)', 'a81a22efb5fafe93f84458a5b851725b0392d5d06eb95c0edf589e06e1bcc6e1'),
('Misión de perímetro en G9 (Briefing paintball) — objeto: caja estanca', 'Buscar un objetivo de interés y llamar a hq para avisar que han encontrado dicho objetivo. Hacer el perímetro hasta que HQ diga algo. Si lo han encontrado rápido avisar al otro enlace para que le meta caña y haya conflicto.', 'ca134e62f0178340501f110e275d3c648bbe4f10676a2371e2752ea9319032fb'),
('Emboscada — interceptar vehículo enemigo — 25_07_26', 'Debéis detectar el vehículo enemigo que parece ser quien os ha jodido la entrega. Una vez allí emboscadlo para después sacarle información a sus integrantes sobre las intenciones que tiene con los especímenes infectados.', 'c350340644a16c403a2962b37ddeef0cc56dbdddca12d37eda19fb8b598e3731'),
('Bolsas de sangre — recuperar muestras infectadas', 'Al parecer alguien ha saboteado nuestra recogida. Hay que encontrar las bolsas de sangre con las muestras que han sido recogidas de diferentes especímenes. Investiguemos las ubicaciones próximas. El mando exige que nos apresuremos antes de que caigan en manos indebidas.', '28649abb9fa33718f2d8352a0f2f66f5c162e85f9bb0be4ccd49ccf560de8de2'),
('Parabólica — asegurar comunicación con VIP', 'Hay que hablar con un VIP y necesitamos asegurar la zona para conseguir una llamada segura. No podemos arriesgarnos a perder la transmisión antes de decirle las coordenadas exactas de los puntos de extracción de las muestras.', 'eac7e2164b93606a6b6c5489220ab669fe34f2fe2f23ee2b04d686fcd877c1f2'),
('Bomba con temporizador — laboratorio principal', 'El VIP os ha dado la ubicación del laboratorio principal, debéis llegar hasta allí y detonar la carga. Recordad que antes de empezar la cuenta atrás deberá guardar reposo 5 minutos.', 'a3d9bf96974a156d1211151370bf78081309c64ccfc532300ae6b77ffe60dc91'),
('Secuestro conductor', 'Hay que espabilarse y encontrar un vehículo para extraer. Es muy probable que esté custodiado y que no sea tarea fácil. Plantear diferentes posibilidades. Una vez lo tengaís avisar a HQ para que os indique la zona de húida.', 'c572db5bf7476d78821894b67fcb763b54e14be71731dd7d314b078f384ade04'),
('Misión de perímetro en CP — objeto: caja fruta', 'Buscar un objetivo de interés y llamar a hq para avisar que han encontrado dicho objetivo. Hacer el perímetro hasta que HQ diga algo. Si lo han encontrado rápido avisar al otro enlace para que le meta caña y haya conflicto.', 'a5d261694e112a8922cd88f3c1aadbfedd3a520e43aee14553ae95913287577f'),
('Viales — muestras del terreno contaminado', 'Deben de buscar cinco muestras del terreno contaminado para poder identificarlo en laboratorio. Recordad indicar donde se han recogido. Esto no depende solo de estas muestras. Hay algo más grave detrás de esto.', '87eaa9302ab09e3512e062ddef92161c08dc55ee819a4310e22ed301917fa6e5'),
('Emboscada — interceptar vehículo enemigo — 27_06_26', 'Debéis detectar el vehículo del VIP que parece ser quien os ha jodido la entrega. Una vez allí emboscadlo para después sacarle información sobre las intenciones que tiene con los especímenes infectados.', 'e3ef2155ccd36f49f21a604213264481ef92f7957e2cf28fdf9ee122a41e37ce'),
('Baja confirmada', 'Os han informado que han detectado una conversación del enemigo. Van a destruïr las reservas del laboratorio. Hay que evitarlo a toda costa. Dirigios de inmediato y abatir a todo el equipo enemigo. Registradles y identificar de qué va esto.', '050f552e586bfb6de350164d5356295ddcfadc1240731fc189790070dfe4d333')
ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash);

CREATE TABLE IF NOT EXISTS hq_missions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  operation_id INT UNSIGNED NOT NULL,
  team ENUM('alpha', 'bravo') NOT NULL,
  title VARCHAR(180) NOT NULL,
  briefing TEXT NOT NULL,
  status ENUM('draft', 'active', 'completed', 'failed', 'deleted') NOT NULL DEFAULT 'draft',
  acknowledged_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY hq_missions_operation_team (operation_id, team, id),
  catalog_id INT UNSIGNED DEFAULT NULL,
  is_imported TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT hq_missions_catalog_fk FOREIGN KEY (catalog_id) REFERENCES hq_mission_catalog(id) ON DELETE SET NULL,
  CONSTRAINT hq_missions_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  operation_id INT UNSIGNED NOT NULL,
  team ENUM('alpha', 'bravo') NOT NULL,
  sender ENUM('hq', 'alpha', 'bravo') NOT NULL,
  body VARCHAR(1500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY hq_messages_channel (operation_id, team, id),
  CONSTRAINT hq_messages_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_maps (
 operation_id INT UNSIGNED NOT NULL,
 annotations MEDIUMBLOB NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (operation_id),
 CONSTRAINT hq_maps_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
