-- 0028 lookup-indexes: down. The indexes go; no row changes either way.
ALTER TABLE `album_artist_lookup` DROP KEY `i_album_artist_lookup_artistid`;
ALTER TABLE `artist_lookup` DROP KEY `i_artist_lookup_artistid`;
ALTER TABLE `feature_lookup` DROP KEY `i_feature_lookup_artistid`;
ALTER TABLE `music_lookup` DROP KEY `i_music_lookup_artistid`;
ALTER TABLE `scratch_lookup` DROP KEY `i_scratch_lookup_artistid`;
ALTER TABLE `remix_lookup` DROP KEY `i_remix_lookup_artistid`;
ALTER TABLE `album_lookup` DROP KEY `i_album_lookup_albumid`;
ALTER TABLE `albums` DROP KEY `i_albums_labelid`;
ALTER TABLE `songs` DROP KEY `i_songs_viewed`;
