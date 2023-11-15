<?php

namespace FoF\AntiSpam\Tests\integration\api;

use FoF\AntiSpam\Concerns\Content;
use FoF\AntiSpam\Filter;
use FoF\AntiSpam\Tests\AntiSpamTestCase;

class UniqueExamplesTest extends AntiSpamTestCase
{
    use Content;

    /**
     * @test
     * @coversNothing
     */
    function fails_on_example_from_discuss_2022_08_26()
    {
        (new Filter)
            ->allowLinksFromDomain('flarum.org')
            ->allowLinksFromDomain('github.com')
            ->allowLinksFromDomain('blomstra.net')
            ->allowLinksFromDomain('extiverse.com')
            ->allowLinksFromDomain('blomstra.community')
            ->allowLinksFromDomain('kilowhat.net')
            ->allowLinksFromDomain('opencollective.org')
            ->allowLinksFromDomain('packagist.com');

        $this->assertTrue(
            $this->containsProblematicContent(
                <<<EOM
If you are looking for the best and most affordable car rental service, book Chandigarh to Delhi taxi at Vahan Seva. We are a renowned and reliable car rental agency in Chandigarh. We are famous for offering the best cab booking services to our clients. You can find various **[Chandigarh to Delhi Taxi Services](https://jkbrothertravels.com/oneway/taxi-service/chandigarh-to-delhi)** available from where you can book your cab from Chandigarh to Delhi. for more information visit our website.
EOM
            )
        );
    }

    /**
     * @test
     * @coversNothing
     * @see https://discuss.flarum.org/d/31524/5
     */
    function fails_on_example_from_discuss_amazon_image()
    {
        (new Filter)
            ->allowLinksFromDomain('pianoclack.s3.us-east-1.amazonaws.com');

        $this->assertFalse(
            $this->containsProblematicContent(
                <<<EOM
https://pianoclack.s3.us-east-1.amazonaws.com/2022-05-16/1652706371-369258-6931c6ee-525b-4025-b1c1-db1fb2d0d7b6.jpg
EOM
            )
        );
        $this->assertFalse(
            $this->containsProblematicContent(
                <<<EOM
https://pianoclack.s3.us-east-1.amazonaws.com/2022-09-01/1662053787-63955-how-effective-are-mask.jpg
EOM
            )
        );
    }

    /**
     * @test
     * @coversNothing
     * @see https://discuss.flarum.org/d/31524/63
     */
    function allows_when_post_contains_one_allowed_domain()
    {
        (new Filter)
            ->allowLinksFromDomain('youtube.com')
            ->allowLinksFromDomain('youtu.be')
            ->allowLinksFromDomain('pianoclack.com')
            ->allowLinksFromDomain('pianoclack.s3.us-east-1.amazonaws.com');

        $this->assertTrue(
            $this->containsProblematicContent(
                <<<EOM
They've already mentioned Yuja and Khatia (who I'd nominate for the most \*Khurvaceous Bach-\*side). Here are three more candidates: Connie Han [Jazz pianist](https://www.facebook.com/conniehanjazz/videos/738541330863782) ; Riyoko Takagi [Autumn Leaves](https://www.youtube.com/watch?v=shD0270uII0) ; Donka Angatscheva : [Classical trio](https://www.youtube.com/watch?v=1fXDFp3qx7k) . PS- No bunnies were harmed during the production of Riyoko's performance.
EOM
            )
        );
    }
}
