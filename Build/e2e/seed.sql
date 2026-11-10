INSERT INTO pages (uid, pid, sorting, title, slug, doktype, hidden, is_siteroot) VALUES
    (1, 0, 1, 'Backend', '/', 1, 0, 1),
    (2, 0, 2, 'Site B', '/', 1, 0, 1),
    (3, 0, 3, 'Site C', '/', 1, 0, 1),
    (4, 2, 1, 'Hidden page on site B', '/hidden', 1, 1, 0),
    (5, 3, 1, 'Hidden page on site C', '/hidden', 1, 1, 0),
    (6, 0, 4, 'Site D', '/', 1, 0, 1),
    (7, 6, 1, 'Hidden page on site D', '/hidden', 1, 1, 0);

INSERT INTO tt_content (uid, pid, sorting, CType, header, colPos) VALUES
    (123, 4, 1, 'header', 'Section far down', 0);
