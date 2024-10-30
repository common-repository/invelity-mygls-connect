<?php

namespace setasign\Fpdi;

use setasign\Fpdi\Fpdi;

class Rotate extends Fpdi
{
    /**
     * @var		mixed	$angl
     */
    var $angle=0;

    /**
     * Rotate.
     *
     * @author	Martin Halaj
     * @since	v0.0.1
     * @version	v1.0.0	Thursday, March 11th, 2021.
     * @param	mixed	$angle	
     * @param	mixed	=-1   	
     * @param	mixed	=-1   	
     * @return	void
     */
    function Rotate($angle,$x=-1,$y=-1)
    {
        if($x==-1)
            $x=$this->x;
        if($y==-1)
            $y=$this->y;
        if($this->angle!=0)
            $this->_out('Q');
        $this->angle=$angle;
        if($angle!=0)
        {
            $angle*=M_PI/180;
            $c=cos($angle);
            $s=sin($angle);
            $cx=$x*$this->k;
            $cy=($this->h-$y)*$this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
        }
    }

    /**
     * _endpage.
     *
     * @author	Martin Halaj
     * @since	v0.0.1
     * @version	v1.0.0	Thursday, March 11th, 2021.
     * @return	void
     */
    function _endpage()
    {
        if($this->angle!=0)
        {
            $this->angle=0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    /**
     * RotatedText.
     *
     * @author	Martin Halaj 
     * @since	v0.0.1
     * @version	v1.0.0	Thursday, March 11th, 2021.
     * @param	mixed	$x    	
     * @param	mixed	$y    	
     * @param	mixed	$txt  	
     * @param	mixed	$angle	
     * @return	void
     */
    function RotatedText($x,$y,$txt,$angle)
    {
        //Text rotated around its origin
        $this->Rotate($angle,$x,$y);
        $this->Text($x,$y,$txt);
        $this->Rotate(0);
    }

    /**
     * RotatedImage.
     *
     * @author	Martin Halaj
     * @since	v0.0.1
     * @version	v1.0.0	Thursday, March 11th, 2021.
     * @param	mixed	$file 	
     * @param	mixed	$x    	
     * @param	mixed	$y    	
     * @param	mixed	$w    	
     * @param	mixed	$h    	
     * @param	mixed	$angle	
     * @return	void
     */
    function RotatedImage($file,$x,$y,$w,$h,$angle)
    {
        //Image rotated around its upper-left corner
        $this->Rotate($angle,$x,$y);
        $this->Image($file,$x,$y,$w,$h);
        $this->Rotate(0);
    }
}