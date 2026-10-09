-- 0028 lookup-indexes: up. The indexes the pages' joins lacked (#69). The link tables were keyed
-- with the album or the song first (0011), so a lookup by artist read the whole table: an
-- artist's albums, their counts in the artist lists (the 47 full joins of a crawl of the site
-- on a copy of production), the songs an artist produced, cut or featured on. An album's
-- tracklist read all of album_lookup for want of an index on the album; a label's albums all
-- of albums; the most viewed songs every song.
ALTER TABLE `album_artist_lookup` ADD KEY `i_album_artist_lookup_artistid` (`artistid`);
ALTER TABLE `artist_lookup` ADD KEY `i_artist_lookup_artistid` (`artistid`);
ALTER TABLE `feature_lookup` ADD KEY `i_feature_lookup_artistid` (`artistid`);
ALTER TABLE `music_lookup` ADD KEY `i_music_lookup_artistid` (`artistid`);
ALTER TABLE `scratch_lookup` ADD KEY `i_scratch_lookup_artistid` (`artistid`);
ALTER TABLE `remix_lookup` ADD KEY `i_remix_lookup_artistid` (`artistid`);
ALTER TABLE `album_lookup` ADD KEY `i_album_lookup_albumid` (`albumid`, `disc`, `track`);
ALTER TABLE `albums` ADD KEY `i_albums_labelid` (`labelid`);
ALTER TABLE `songs` ADD KEY `i_songs_viewed` (`viewed`);
