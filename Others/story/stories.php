<?php
// stories.php - Page with moral stories for kids adapted from KidsTut
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 30px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(78, 115, 223, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: #4e73df;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #4e73df;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 2;
            border: none;
        }

        .back-button:hover {
            background: #2e59d9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
            color: white;
            text-decoration: none;
        }

        /* Story Cards */
        .story-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: all 0.3s ease;
            position: relative;
        }

        .story-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .story-image-container {
            position: relative;
            overflow: hidden;
            height: 250px;
            background: linear-gradient(135deg, #4e73df, #764ba2);
        }

        .story-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .story-card:hover .story-image {
            transform: scale(1.05);
        }

        .story-content {
            padding: 25px;
        }

        .story-title {
            color: #4e73df;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.5rem;
            position: relative;
        }

        .story-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #4e73df, #764ba2);
            border-radius: 2px;
        }

        .story-meta {
            color: #858796;
            font-size: 0.85rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(78, 115, 223, 0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .story-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .story-text {
            color: #5a5c69;
            font-size: 1rem;
            line-height: 1.7;
        }

        .story-text p {
            margin-bottom: 1.2rem;
            text-align: justify;
        }

        .story-moral {
            background: linear-gradient(135deg, rgba(78, 115, 223, 0.1), rgba(118, 75, 162, 0.1));
            border-left: 4px solid #4e73df;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            font-style: italic;
            position: relative;
            overflow: hidden;
        }

        .story-moral::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .story-card:hover .story-moral::before {
            transform: translateX(100%);
        }

        .story-moral strong {
            color: #4e73df;
            font-size: 1.1rem;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(78, 115, 223, 0.3);
            border-radius: 50%;
            border-top-color: #4e73df;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Scroll to Top Button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #4e73df;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            z-index: 1000;
        }

        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            background: #2e59d9;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(78, 115, 223, 0.4);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .back-button {
                position: static;
                margin-bottom: 20px;
                align-self: flex-start;
            }

            .story-meta {
                flex-direction: column;
                gap: 8px;
            }

            .story-image-container {
                height: 200px;
            }

            .story-content {
                padding: 20px;
            }
        }

        /* Story Grid for better layout */
        @media (min-width: 1200px) {
            .stories-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }

            .story-card {
                margin-bottom: 0;
            }
        }

        /* Additional animations */
        .story-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .story-card:nth-child(even) {
            animation-delay: 0.1s;
        }

        .story-card:nth-child(odd) {
            animation-delay: 0.2s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="page-header">
        <a href="../dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1>Moral Stories for Kids</h1>
        <p>Discover timeless tales that teach valuable life lessons</p>
    </div>

    <div class="container">
        <div class="stories-grid">
            <!-- Story 1: The Boy Who Cried Wolf -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story1.png" alt="The Boy Who Cried Wolf" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Boy Who Cried Wolf</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 5 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>Once upon a time, there was a shepherd boy who lived in a village near a forest. Every day,
                            he would take his flock of sheep to graze on a hill near the forest. It was a quiet job, and
                            the boy often felt lonely and bored.</p>

                        <p>One day, the boy decided to play a trick on the villagers. He ran down the hill shouting,
                            "Wolf! Wolf! A wolf is chasing the sheep!"</p>

                        <p>The villagers heard his cries and quickly ran up the hill to help him drive the wolf away.
                            But when they reached the top of the hill, they found no wolf. The boy laughed at the sight
                            of their angry faces.</p>

                        <p>"Don't cry 'wolf', boy," said the villagers, "when there's no wolf!" They went back down the
                            hill, grumbling about being tricked.</p>

                        <p>The next day, the boy played the same trick again. He shouted, "Wolf! Wolf!" again, and once
                            more the villagers rushed up the hill to help him, only to find that there was no wolf. The
                            boy laughed at them again, and the villagers warned him a second time not to cry wolf when
                            there wasn't one.</p>

                        <p>A few days later, a real wolf appeared and began to chase the sheep. The boy was very alarmed
                            and cried out as loudly as he could, "Wolf! Wolf! Please help! The wolf is chasing the
                            sheep!"</p>

                        <p>But this time, the villagers thought he was trying to fool them again and did not come to
                            help. By evening, when the boy didn't return with the sheep, the villagers went up the hill
                            to look for him.</p>

                        <p>They found the boy sitting under a tree with tears in his eyes. "There really was a wolf
                            here! The wolf has scattered my entire flock. I cried out for help, but no one came," he
                            said.</p>

                        <p>An old man approached him and said, "Nobody believes a liar even when he tells the truth."
                        </p>

                        <div class="story-moral">
                            <strong>Moral:</strong> Lying breaks trust. Even if you're telling the truth, no one
                            believes a liar.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 2: The Golden Egg -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story2.png" alt="The Golden Egg" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Golden Egg</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 5 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>Once upon a time, there lived a farmer who had a goose. One day, to his surprise, he found
                            that the goose had laid a golden egg.</p>

                        <p>At first, the farmer thought it was some kind of joke. He nearly threw the egg away. But at
                            the last moment, he decided to have it tested first, and to his astonishment, he discovered
                            that the egg was made of pure gold.</p>

                        <p>The farmer couldn't believe his good fortune. He became even more surprised the next day when
                            the goose laid another golden egg, and the day after – it was the same thing again. Day
                            after day, the farmer gathered one golden egg after another.</p>

                        <p>He soon began to grow rich from selling the eggs. As his wealth grew, so did his greed and
                            impatience. "Why should I have to wait for the goose to lay a golden egg every day?" the
                            farmer thought. "I should be able to get all the eggs at once and become the richest man in
                            the world right now!"</p>

                        <p>With these thoughts in mind, the farmer came up with what he thought was a brilliant plan. He
                            decided to kill the goose and cut it open to get all the golden eggs that must be inside.
                        </p>

                        <p>So, the farmer killed the goose and eagerly cut it open, expecting to find it full of gold.
                            But to his great shock and disappointment, there was nothing inside the goose but ordinary
                            goose parts. There were no golden eggs inside, and now, there was no goose to lay any more
                            golden eggs either.</p>

                        <p>The farmer had destroyed the source of his wealth in his greed for more.</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> Greed often leads to loss. Be satisfied with what you have and don't
                            let greed destroy your source of happiness.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 3: The Lion and the Mouse -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story3.png" alt="The Lion and the Mouse" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Lion and the Mouse</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 5 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>Once upon a time, a mighty lion was taking a nap in the forest. A tiny mouse was scampering
                            about and accidentally ran across the lion's nose, waking him up.</p>

                        <p>The lion was furious at being disturbed and caught the mouse with his paw, ready to eat him.
                            "Please, Your Majesty," squeaked the frightened mouse, "spare my life! I'm so small, I
                            wouldn't make much of a meal for you. If you let me go, I promise I'll repay your kindness
                            someday!"</p>

                        <p>The idea of a tiny mouse helping a mighty lion made the lion laugh. "You? Help me? How could
                            a tiny creature like you ever help me?" But the lion was feeling generous that day, so he
                            opened his paw and let the mouse go.</p>

                        <p>"Thank you, Your Majesty! You won't regret it!" the mouse called as he scurried away.</p>

                        <p>A few days later, the lion was hunting in the forest when he was captured in a hunter's net.
                            He struggled and roared, but could not free himself. The more he fought against the ropes,
                            the tighter they became.</p>

                        <p>The mouse heard the lion's roars and recognized his voice. He immediately ran to where the
                            lion was trapped. "Don't worry, Your Majesty," said the mouse. "I'll get you out of this
                            net."</p>

                        <p>The lion doubted that the small mouse could help, but he had no other hope. The mouse began
                            to gnaw at the ropes with his sharp teeth. It took some time, but the mouse eventually
                            chewed through enough of the ropes for the lion to break free.</p>

                        <p>"Thank you, little friend," said the lion, now humbled. "You have saved my life, just as you
                            promised. I was wrong to laugh at you. Even the smallest creatures can be of great help."
                        </p>

                        <p>"You're welcome, Your Majesty," said the mouse. "You see, even a little mouse can help a
                            mighty lion!"</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> A kindness is never wasted, and we should never underestimate
                            others' abilities regardless of their size or appearance.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 4: The Thirsty Crow -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story4.png" alt="The Thirsty Crow" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Thirsty Crow</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 4 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>It was a hot summer day, and a crow was flying around looking for water. He had been flying
                            for a long time and was very thirsty. His throat was parched, and he felt weak from the heat
                            and lack of water.</p>

                        <p>As he flew over a village, he spotted a pitcher of water in a garden. The crow was delighted
                            and quickly flew down to drink from it. But when he reached the pitcher, he found that it
                            was only half full. The water level was too low, and his beak couldn't reach it.</p>

                        <p>The crow tried to push the pitcher over, hoping that the water would spill out so he could
                            drink it. But the pitcher was too heavy for him to tip over. He tried to break it by pecking
                            at it, but the pitcher was made of strong earthenware and wouldn't break.</p>

                        <p>The crow was getting more desperate with each passing moment. He thought hard about how to
                            reach the water, and then he had an idea. He noticed some pebbles nearby.</p>

                        <p>The crow began picking up the pebbles one by one with his beak and dropping them into the
                            pitcher. With each pebble, the water level rose a little bit. He continued this process
                            patiently, dropping pebble after pebble into the pitcher.</p>

                        <p>Finally, after adding many pebbles, the water level rose high enough for the crow to reach it
                            with his beak. The crow drank the water, quenching his thirst, and flew away happily.</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> Where there's a will, there's a way. Intelligence and perseverance
                            can solve difficult problems.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 5: The Fox and the Grapes -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story5.png" alt="The Fox and the Grapes" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Fox and the Grapes</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 3 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>Once upon a time, a fox was wandering through the countryside. It was a hot summer day, and
                            the fox was feeling extremely thirsty and hungry. He had been searching for food and water
                            for hours but had found nothing.</p>

                        <p>As he walked along, he came upon a vineyard. Looking up, he saw clusters of juicy grapes
                            hanging from a vine that was supported by a tall trellis. The grapes looked ripe, juicy, and
                            delicious. The fox's mouth watered at the sight.</p>

                        <p>"Just what I need to quench my thirst and satisfy my hunger," thought the fox, licking his
                            lips.</p>

                        <p>He backed up a few paces, ran, and jumped to reach the grapes, but they were too high. He
                            couldn't even touch them with the tip of his tail. He tried again, backing up a bit more and
                            leaping with all his might, but still, he couldn't reach the grapes.</p>

                        <p>The fox tried repeatedly, jumping and stretching as high as he could, but the grapes remained
                            tantalizingly out of reach. He gave it one last, mighty effort, leaping with all his
                            strength, but fell short once again.</p>

                        <p>Eventually, the fox gave up. As he walked away, he muttered to himself, "I'm sure those
                            grapes are sour anyway. They're not ripe enough to eat. I don't want them."</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> It's easy to despise what you cannot have. People sometimes pretend
                            to dislike something because they cannot obtain it.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 6: The Ant and the Grasshopper -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story6.png" alt="The Ant and the Grasshopper" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Ant and the Grasshopper</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 5 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>In a field on a warm summer day, a grasshopper was hopping about, chirping and singing
                            happily. An ant passed by, struggling to carry a large kernel of corn to its nest.</p>

                        <p>"Why not come and chat with me," said the grasshopper, "instead of working so hard?"</p>

                        <p>"I'm storing food for the winter," said the ant, "and I suggest you do the same."</p>

                        <p>"Why worry about winter? It's summer now, and there's plenty of food," said the grasshopper,
                            showing the ant the abundant grass and seeds around them.</p>

                        <p>The ant continued its work, gathering food grain by grain and storing it carefully in its
                            nest. All summer long, the ant worked hard, preparing for the cold months ahead, while the
                            grasshopper spent his days singing, dancing, and enjoying the sun.</p>

                        <p>When winter came, the fields were covered with snow, and there was no food to be found. The
                            grasshopper, hungry and cold, saw the ant distributing corn and grain from its stores to its
                            fellow ants in their warm, cozy nest.</p>

                        <p>The grasshopper had nothing to eat and nowhere warm to stay. He realized too late the wisdom
                            of the ant's hard work and preparation.</p>

                        <p>Hungry and cold, the grasshopper knocked at the door of the ant's nest and begged for
                            something to eat.</p>

                        <p>"What were you doing all summer when I was working hard to store food?" asked the ant.</p>

                        <p>"I was singing and enjoying the beautiful days," replied the grasshopper.</p>

                        <p>"Then you can dance all winter to your singing," said the ant. However, being kind-hearted,
                            the ant shared some food with the grasshopper but advised him to plan ahead next time.</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> It is wise to prepare today for the needs of tomorrow. Hard work and
                            preparation pay off in times of need.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 7: The Tortoise and the Hare -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story7.png" alt="The Tortoise and the Hare" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Tortoise and the Hare</h2>
                    <div class="story-meta">
                        <span><i class="fas fa-clock"></i> 6 minutes</span>
                        <span><i class="fas fa-tag"></i> Moral Story</span>
                        <span><i class="fas fa-user"></i> KidsTut</span>
                    </div>
                    <div class="story-text">
                        <p>Once upon a time, a hare was making fun of a tortoise for being so slow.</p>

                        <p>"Do you ever get anywhere?" the hare asked with a laugh.</p>

                        <p>"Yes," replied the tortoise, "and I get there sooner than you think. I'll run a race with you
                            to prove it."</p>

                        <p>The hare was much amused by this challenge. He agreed to the race, thinking it would be an
                            easy win and good for a laugh.</p>

                        <p>A course was set and the race began. The hare sprinted ahead very quickly and was soon far
                            out of sight. Seeing that he was so far ahead, he thought, "I have plenty of time to rest
                            before the slow tortoise catches up."</p>

                        <p>So he lay down under a shady tree and soon fell asleep.</p>

                        <p>Meanwhile, the tortoise kept going, slowly but steadily. He never stopped, never looked back,
                            and focused only on the finish line ahead.</p>

                        <p>The hare slept longer than he had intended. When he finally woke up, he saw the tortoise was
                            very near the finish line. He ran as fast as he could, but it was too late. The tortoise had
                            already crossed the finish line and won the race.</p>

                        <p>The tortoise looked back at the surprised and disappointed hare and said, "Slow and steady
                            wins the race."</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> Slow and steady wins the race. Consistent effort is more important
                            than speed when working toward a goal.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Story 8: The Shepherd Boy and the Wolf -->
            <div class="story-card">
                <div class="story-image-container">
                    <img src="story8.png" alt="The Shepherd Boy and the Wolf" class="story-image">
                </div>
                <div class="story-content">
                    <h2 class="story-title">The Shepherd Boy and the Wolf</h2>
                    <div class="story-meta">
                        <i class="fas fa-clock"></i> Reading time: 5 minutes &nbsp; | &nbsp;
                        <i class="fas fa-tag"></i> Category: Moral Story &nbsp; | &nbsp;
                        <i class="fas fa-user"></i> Source: KidsTut
                    </div>
                    <div class="story-text">
                        <p>Once upon a time, there was a shepherd boy who tended his flock of sheep not far from a
                            village.
                            The boy was often lonely, and he wished for some company and excitement.</p>

                        <p>One day, he thought of a plan to get some attention and amusement. He ran down toward the
                            village
                            calling out, "Wolf! Wolf! A wolf is chasing the sheep!"</p>

                        <p>The villagers, who were working in the fields, heard his cries and rushed to help him drive
                            the
                            wolf away. But when they arrived, they saw no wolf, only the boy laughing at them. The
                            villagers
                            were very angry at being tricked and warned the boy not to cry wolf again when there wasn't
                            one.
                        </p>

                        <p>A few days later, the shepherd boy played the same trick again. He shouted, "Wolf! Wolf!" The
                            villagers heard him and ran up the hill to help him, but again found no wolf. The boy
                            laughed at
                            their annoyance.</p>

                        <p>"Don't cry 'wolf' when there isn't a wolf, boy!" they said sternly. "Nobody believes a liar,
                            even
                            when he's telling the truth!"</p>

                        <p>But the boy just grinned and watched them go back down the hill once more.</p>

                        <p>Later that day, a real wolf approached the flock. The boy was very frightened and ran toward
                            the
                            village shouting, "Wolf! Wolf! Please help! The wolf is really here this time!"</p>

                        <p>But the villagers thought he was trying to fool them again and did not come to help. The wolf
                            attacked the flock and scattered the sheep in all directions.</p>

                        <p>That evening, when the boy didn't return with the sheep, the villagers went up the hill to
                            look
                            for him. They found him weeping.</p>

                        <p>"There really was a wolf here!" the boy cried. "It scattered the flock, and I lost several
                            sheep."</p>

                        <p>"Nobody believes a liar," they told him, "even when he's telling the truth."</p>

                        <div class="story-moral">
                            <strong>Moral:</strong> If you often tell lies, people won't believe you even when you're
                            telling the truth. Honesty is the best policy.
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</body>

</html>