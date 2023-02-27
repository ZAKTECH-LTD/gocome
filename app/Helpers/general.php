<?php

function isRTL(): bool
{
    return app()->getLocale() == "ar" || setting('localeCode') == "ar";
}

