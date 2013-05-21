<?php

class ARC2_NQuadsParser extends ARC2_TurtleParser{
    
    function __construct($a, &$caller) {
        parent::__construct($a, $caller);
    }
    
    function parse($path, $data = '', $iso_fallback = false) {
        $this->setDefaultPrefixes();
        /* reader */
        if (!$this->v('reader')) {
            ARC2::inc('Reader');
            $this->reader = new ARC2_Reader($this->a, $this);
        }
        $this->reader->setAcceptHeader('Accept: application/x-turtle; q=0.9, */*; q=0.1');
        $this->reader->activate($path, $data);
        $this->base = $this->v1('base', $this->reader->base, $this->a);
        $this->r = array('vars' => array());
        /* parse */
        $buffer = '';
        $more_triples = array();
        $sub_v = '';
        $sub_v2 = '';
        $loops = 0;
        $prologue_done = 0;
        while ($d = $this->reader->readStream(0, 8192)) {
            $buffer .= $d;
            $sub_v = $buffer;
            do {
                $proceed = 0;
                
                if ((list($sub_r, $sub_v, $more_triples, $sub_v2) = $this->xQuadsBlock($sub_v)) && is_array($sub_r)) {
                    $proceed = 1;
                    $loops = 0;
                    foreach ($sub_r as $t) {
                        $this->addT($t);
                    }
                }
            } while ($proceed);
            $loops++;
            $buffer = $sub_v;
            if ($loops > $this->max_parsing_loops) {/* most probably a parser or code bug, might also be a huge object value, though */
                $this->addError('too many loops: ' . $loops . '. Could not parse "' . substr($buffer, 0, 200) . '..."');
                break;
            }
        }
        foreach ($more_triples as $t) {
            $this->addT($t);
        }
        $sub_v = count($more_triples) ? $sub_v2 : $sub_v;
        $buffer = $sub_v;
        $this->unparsed_code = $buffer;
        $this->reader->closeStream();
        unset($this->reader);
        /* remove trailing comments */
        while (preg_match('/^\s*(\#[^\xd\xa]*)(.*)$/si', $this->unparsed_code, $m)) $this->unparsed_code = $m[2];
        if ($this->unparsed_code && !$this->getErrors()) {
            $rest = preg_replace('/[\x0a|\x0d]/i', ' ', substr($this->unparsed_code, 0, 30));
            if (trim($rest)) $this->addError('Could not parse "' . $rest . '"');
        }
        return $this->done();
    }
    
    function xQuadsBlock($v) {
        $pre_r = array();
        $r = array();
        $state = 1;
        $sub_v = $v;
        $buffer = $sub_v;
        do {
            $proceed = 0;
            if ($state == 1) {/* expecting subject */
                $t = array('type' => 'quad', 's' => '', 'p' => '', 'o' => '', 's_type' => '', 'p_type' => '', 'o_type' => '', 'o_datatype' => '', 'o_lang' => '');
                if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    $t['s'] = $sub_r['value'];
                    $t['s_type'] = $sub_r['type'];
                    $state = 2;
                    $proceed = 1;
                }
                elseif ((list($sub_r, $sub_v) = $this->xCollection($sub_v)) && $sub_r) {
                    $t['s'] = $sub_r['id'];
                    $t['s_type'] = $sub_r['type'];
                    $pre_r = array_merge($pre_r, $sub_r['triples']);
                    $state = 2;
                    $proceed = 1;
                    if ($sub_r = $this->x('\.', $sub_v)) {
                        $this->addError('DOT after subject found.');
                    }
                }
                elseif ((list($sub_r, $sub_v) = $this->xBlankNodePropertyList($sub_v)) && $sub_r) {
                    $t['s'] = $sub_r['id'];
                    $t['s_type'] = $sub_r['type'];
                    $pre_r = array_merge($pre_r, $sub_r['triples']);
                    $state = 2;
                    $proceed = 1;
                }
                elseif ($sub_r = $this->x('\.', $sub_v)) {
                    $this->addError('Subject expected, DOT found.' . $sub_v);
                }
            }
            if ($state == 2) {/* expecting predicate */
                if ($sub_r = $this->x('a\s+', $sub_v)) {
                    $sub_v = $sub_r[1];
                    $t['p'] = $this->rdf . 'type';
                    $t['p_type'] = 'uri';
                    $state = 3;
                    $proceed = 1;
                }
                elseif ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    if ($sub_r['type'] == 'bnode') {
                        $this->addError('Blank node used as triple predicate');
                    }
                    $t['p'] = $sub_r['value'];
                    $t['p_type'] = $sub_r['type'];
                    $state = 3;
                    $proceed = 1;
                }
                elseif ($sub_r = $this->x('\.', $sub_v)) {
                    $this->addError('\. encountered where a predicate is expected');
                }
            }
            if ($state == 3) {/* expecting object */
                if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    $t['o'] = $sub_r['value'];
                    $t['o_type'] = $sub_r['type'];
                    $t['o_lang'] = $this->v('lang', '', $sub_r);
                    $t['o_datatype'] = $this->v('datatype', '', $sub_r);
                    $pre_r[] = $t;
                    $state = 4;
                    $proceed = 1;
                }
                elseif ((list($sub_r, $sub_v) = $this->xCollection($sub_v)) && $sub_r) {
                    $t['o'] = $sub_r['id'];
                    $t['o_type'] = $sub_r['type'];
                    $pre_r = array_merge($pre_r, array($t), $sub_r['triples']);
                    $state = 4;
                    $proceed = 1;
                }
                elseif ((list($sub_r, $sub_v) = $this->xBlankNodePropertyList($sub_v)) && $sub_r) {
                    $t['o'] = $sub_r['id'];
                    $t['o_type'] = $sub_r['type'];
                    $pre_r = array_merge($pre_r, array($t), $sub_r['triples']);
                    $state = 4;
                    $proceed = 1;
                }
            }
            if ($state == 4){/* expecting context */
                if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    if ($sub_r['type'] == 'bnode') {
                        $this->addError('Blank node used as graph name');
                    }
                    $t['c'] = $sub_r['value'];
                    $t['c_type'] = $sub_r['type'];
                    $state = 5;
                    $proceed = 1;
                }
            }         
            if ($state == 5) {/* expecting . or ; or , or } */
                if ($sub_r = $this->x('\.', $sub_v)) {
                    $sub_v = $sub_r[1];
                    $buffer = $sub_v;
                    $r = array_merge($r, $pre_r);
                    $pre_r = array();
                    $state = 1;
                    $proceed = 1;
                }
            }
        } while ($proceed);
        return count($r) ? array($r, $buffer, $pre_r, $sub_v) : array(0, $buffer, $pre_r, $sub_v);
    }
}

?>