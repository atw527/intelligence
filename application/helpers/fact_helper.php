<?php

function _f($fact)
{
	return get_instance()->m_facts->get_row($fact)->value;
}

/* ?> */
