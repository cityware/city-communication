<?php

namespace Cityware\Communication\Curl;

/**
 * Implements parallel-processing for cURL requests.
 *
 * The PHP cURL library allows two or more requests to run in
 * parallel (at the same time). If you have multiple requests
 * that may have high latency but can then be processed quickly
 * in series (one after the other), then running them at the
 * same time may save time, overall.
 *
 * You must create individual {@link Curl} objects first, add them to
 * the Parallel object, execute the Parallel object,
 * then get the data from the individual {@link Curl} objects. (Yes,
 * it's annoying, but it's limited by the PHP cURL library.)
 *
 * For example:
 *
 * <code>
 * $a = new Curl("http://www.yahoo.com/");
 * $b = new Curl("http://www.microsoft.com/");
 *
 * $m = new Parallel($a, $b);
 *
 * $m->exec(); // Now we play the waiting game.
 *
 * printf("Yahoo is %n characters.\n", strlen($a->fetch()));
 * printf("Microsoft is %n characters.\n", strlen($a->fetch()));
 * </code>
 *
 * You can add any number of {@link Curl} objects to the
 * Parallel object's constructor (including 0), or you
 * can add with the {@link add()} method:
 *
 * <code>
 * $m = new Parallel;
 *
 * $a = new Curl("http://www.yahoo.com/");
 * $b = new Curl("http://www.microsoft.com/");
 *
 * $m->add($a);
 * $m->add($b);
 *
 * $m->exec(); // Now we play the waiting game.
 *
 * printf("Yahoo is %n characters.\n", strlen($a->fetch()));
 * printf("Microsoft is %n characters.\n", strlen($a->fetch()));
 * </code>
 */
class Parallel
{
    /**
     * Store the cURL master resource.
     * @var resource
     */
    protected $mh;

    /**
     * Store the resource handles that were
     * added to the session.
     * @var array
     */
    protected $ch = array();

    /**
     * Store the version number of this class.
     */
    const VERSION = '0.3.0';

    /**
     * Initialize the multisession handler.
     *
     * @uses add()
     * @param  Curl     $curl,... {@link Curl} objects to add to the Parallelizer.
     * @return Parallel
     */
    public function __construct()
    {
        $this->mh = curl_multi_init();

        foreach (func_get_args() as $ch) {
            $this->add($ch);
        }

        return $this;
    }

    /**
     * On destruction, frees resources.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Close the current session and free resources.
     */
    public function close()
    {
        foreach ($this->ch as $ch) {
            curl_multi_remove_handle($this->mh, $ch);
        }
        curl_multi_close($this->mh);
    }

    /**
     * Add a {@link Curl} object to the Parallelizer.
     *
     * Will throw a catchable fatal error if passed a non-Curl object.
     *
     * @uses Curl::grant()
     * @uses Parallel::accept()
     * @param Curl $ch Curl object.
     */
    public function add(Curl $ch)
    {
        // get the protected resource
        $ch->grant($this);
    }

    /**
     * Remove a {@link Curl} object from the Parallelizer.
     *
     * @param Curl $ch Curl object.
     * @uses Curl::revoke()
     * @uses Parallel::release()
     */
    public function remove(Curl $ch)
    {
        $ch->revoke($this);
    }

    /**
     * Execute the parallel cURL requests.
     */
    public function exec()
    {
        do {
            curl_multi_exec($this->mh, $running);
        } while ($running > 0);
    }

    /**
     * Accept a resource handle from a {@link Curl} object and
     * add it to the master.
     *
     * @param resource $ch A resource returned by curl_init().
     */
    public function accept($ch)
    {
        $this->ch[] = $ch;
        curl_multi_add_handle($this->mh, $ch);
    }

    /**
     * Accept a resource handle from a {@link Curl} object and
     * remove it from the master.
     *
     * @param resource $ch A resource returned by curl_init().
     */
    public function release($ch)
    {
        if (false !== $key = array_search($this->ch, $ch)) {
            unset($this->ch[$key]);
            curl_multi_remove_handle($this->mh, $ch);
        }
    }

}
