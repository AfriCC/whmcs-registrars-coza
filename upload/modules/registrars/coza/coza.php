<?php

namespace
{
    function foobar()
    {
        var_dump(urlencode('foo'));
        var_dump(\urlencode('foo bar'));
    }

    function urlencode($str)
    {
        return 'bar';
    }
}