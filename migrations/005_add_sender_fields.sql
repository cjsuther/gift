-- Agrega los campos del "remitente" (quien regala la giftcard) y su email
-- para notificarle cuando el destinatario la canjee.
--
-- Idempotente: si la columna ya existe, el ALTER falla pero ese error no nos
-- preocupa porque el resto del sistema ya está validado contra el schema final.
-- Si llegan a re-correrse las migrations enteras, comentar este file.

ALTER TABLE giftcards
    ADD COLUMN sender_name  VARCHAR(150) DEFAULT NULL AFTER recipient_contact,
    ADD COLUMN sender_email VARCHAR(150) DEFAULT NULL AFTER sender_name;
