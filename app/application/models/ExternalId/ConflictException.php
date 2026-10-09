<?php

/**
 * An external id that already belongs to another catalogue row. The import reports it rather
 * than moving the id, since two rows claiming one id means one of them is a duplicate.
 */
class Model_ExternalId_ConflictException extends RuntimeException
{
}
