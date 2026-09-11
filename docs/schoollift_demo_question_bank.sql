-- Demo SchoolLift question bank installer
-- Target: demo.schoollift.com.ng only
-- Generated from database/migration-audit-dumps/schoollift_demo.sql.
--
-- This installs 10 objective questions into every valid class-arm/subject scope
-- configured in the dump. It is idempotent for exact question text and scope.
-- Back up the database before importing any data script.

SET NAMES utf8mb4;

SET @schoollift_demo_question_bank_site_ok := (
    SELECT IF(COUNT(*) > 0, 1, 0)
    FROM sch_settings
    WHERE LOWER(TRIM(email)) = 'info@demo.schoollift.com.ng'
);

SET @schoollift_demo_question_bank_staff_id := COALESCE(
    (SELECT id FROM staff ORDER BY id LIMIT 1),
    1
);

DROP TEMPORARY TABLE IF EXISTS tmp_schoollift_demo_class_map;
CREATE TEMPORARY TABLE tmp_schoollift_demo_class_map (
    class_id INT NOT NULL,
    expected_class_name VARCHAR(60) NOT NULL,
    audience VARCHAR(30) NOT NULL,
    PRIMARY KEY (class_id)
) ENGINE=InnoDB;

INSERT INTO tmp_schoollift_demo_class_map (class_id, expected_class_name, audience) VALUES
(1, 'Primary 1', 'lower-primary'),
(17, 'Grade 1', 'lower-primary'),
(18, 'year 6', 'year-6'),
(19, 'ss1', 'ss1'),
(22, 'primary B1', 'lower-primary');

DROP TEMPORARY TABLE IF EXISTS tmp_schoollift_demo_question_templates;
CREATE TEMPORARY TABLE tmp_schoollift_demo_question_templates (
    audience VARCHAR(30) NOT NULL,
    subject_id INT NOT NULL,
    expected_subject_name VARCHAR(100) NOT NULL,
    question_type VARCHAR(100) NOT NULL,
    question TEXT NOT NULL,
    opt_a TEXT,
    opt_b TEXT,
    opt_c TEXT,
    opt_d TEXT,
    opt_e TEXT,
    correct TEXT
) ENGINE=InnoDB;

INSERT INTO tmp_schoollift_demo_question_templates
    (audience, subject_id, expected_subject_name, question_type, question, opt_a, opt_b, opt_c, opt_d, opt_e, correct)
VALUES
('lower-primary', 1, 'Basic science', 'singlechoice', 'Which sense organ helps us to see?', 'Eyes', 'Ears', 'Nose', 'Tongue', '', 'opt_a'),
('lower-primary', 1, 'Basic science', 'singlechoice', 'Which of these is a living thing?', 'Stone', 'Chair', 'Bean plant', 'Spoon', '', 'opt_c'),
('lower-primary', 1, 'Basic science', 'singlechoice', 'What is the main natural source of light during the day?', 'Sun', 'Torch', 'Candle', 'Bulb', '', 'opt_a'),
('lower-primary', 1, 'Basic science', 'singlechoice', 'Which organ helps a person to breathe?', 'Lungs', 'Teeth', 'Bones', 'Fingernails', '', 'opt_a'),
('lower-primary', 1, 'Basic science', 'singlechoice', 'What is a young frog called?', 'Tadpole', 'Kitten', 'Calf', 'Chick', '', 'opt_a'),
('lower-primary', 1, 'Basic science', 'singlechoice', 'Which part of a plant absorbs water from the soil?', 'Roots', 'Flower', 'Fruit', 'Leaf', '', 'opt_a'),
('lower-primary', 1, 'Basic science', 'singlechoice', 'Which liquid becomes ice when it is cooled enough?', 'Water', 'Palm oil', 'Kerosene', 'Juice', '', 'opt_a'),
('lower-primary', 1, 'Basic science', 'true_false', 'Living things grow and change.', '', '', '', '', '', 'true'),
('lower-primary', 1, 'Basic science', 'true_false', 'A stone needs food in order to grow.', '', '', '', '', '', 'false'),
('lower-primary', 1, 'Basic science', 'multichoice', 'Select the two living things.', 'Goat', 'Mango tree', 'Football', 'Cup', '', '["opt_a","opt_b"]'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'Which tool is used to drive a nail into wood?', 'Hammer', 'Ruler', 'Spoon', 'Brush', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'Which tool is suitable for cutting paper?', 'Scissors', 'Cup', 'Pencil', 'Plate', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'Which tool helps us to draw a straight line?', 'Ruler', 'Broom', 'Fork', 'Needle', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'What should protect the head on a building site?', 'Safety helmet', 'Sandals', 'Handkerchief', 'Notebook', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'Which material is usually transparent?', 'Clear glass', 'Wood', 'Brick', 'Cardboard', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'Which device gives light when connected to electricity?', 'Electric bulb', 'Door handle', 'Ruler', 'Wheelbarrow', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'singlechoice', 'Which shape is most like a wheel?', 'Circle', 'Triangle', 'Rectangle', 'Square', '', 'opt_a'),
('lower-primary', 2, 'Basic Technology', 'true_false', 'Scissors should be handled carefully.', '', '', '', '', '', 'true'),
('lower-primary', 2, 'Basic Technology', 'true_false', 'It is safe to touch a bare electric wire.', '', '', '', '', '', 'false'),
('lower-primary', 2, 'Basic Technology', 'multichoice', 'Select the two tools.', 'Hammer', 'Screwdriver', 'Orange', 'Plate', '', '["opt_a","opt_b"]'),
('lower-primary', 3, 'French', 'singlechoice', 'What does Bonjour mean in English?', 'Hello', 'Goodbye', 'Please', 'Sorry', '', 'opt_a'),
('lower-primary', 3, 'French', 'singlechoice', 'What does Merci mean in English?', 'Thank you', 'Good night', 'Welcome', 'Excuse me', '', 'opt_a'),
('lower-primary', 3, 'French', 'singlechoice', 'Which French word means one?', 'Un', 'Deux', 'Trois', 'Quatre', '', 'opt_a'),
('lower-primary', 3, 'French', 'singlechoice', 'Which French word means two?', 'Deux', 'Cinq', 'Rouge', 'Chat', '', 'opt_a'),
('lower-primary', 3, 'French', 'singlechoice', 'Which French word means red?', 'Rouge', 'Bleu', 'Vert', 'Noir', '', 'opt_a'),
('lower-primary', 3, 'French', 'singlechoice', 'Which French word means blue?', 'Bleu', 'Jaune', 'Blanc', 'Rose', '', 'opt_a'),
('lower-primary', 3, 'French', 'singlechoice', 'What does chat mean in English?', 'Cat', 'Dog', 'Bird', 'Fish', '', 'opt_a'),
('lower-primary', 3, 'French', 'true_false', 'Au revoir means goodbye.', '', '', '', '', '', 'true'),
('lower-primary', 3, 'French', 'true_false', 'Trois is the French word for five.', '', '', '', '', '', 'false'),
('lower-primary', 3, 'French', 'multichoice', 'Select the two French number words.', 'Un', 'Deux', 'Rouge', 'Chat', '', '["opt_a","opt_b"]'),
('lower-primary', 4, 'English language', 'singlechoice', 'Which letter is a vowel?', 'A', 'B', 'D', 'T', '', 'opt_a'),
('lower-primary', 4, 'English language', 'singlechoice', 'What is the plural of cat?', 'Cats', 'Cates', 'Cat', 'Caties', '', 'opt_a'),
('lower-primary', 4, 'English language', 'singlechoice', 'What is the opposite of hot?', 'Cold', 'Warm', 'Dry', 'Bright', '', 'opt_a'),
('lower-primary', 4, 'English language', 'singlechoice', 'Which mark should end a question?', 'Question mark', 'Full stop', 'Comma', 'Apostrophe', '', 'opt_a'),
('lower-primary', 4, 'English language', 'singlechoice', 'Which word is a naming word?', 'School', 'Run', 'Quickly', 'Blue', '', 'opt_a'),
('lower-primary', 4, 'English language', 'singlechoice', 'Which word rhymes with sun?', 'Fun', 'Sit', 'Top', 'Bed', '', 'opt_a'),
('lower-primary', 4, 'English language', 'singlechoice', 'Which word is correctly spelt?', 'House', 'Hous', 'Howse', 'Houce', '', 'opt_a'),
('lower-primary', 4, 'English language', 'true_false', 'The word dog begins with the letter d.', '', '', '', '', '', 'true'),
('lower-primary', 4, 'English language', 'true_false', 'The opposite of big is tall.', '', '', '', '', '', 'false'),
('lower-primary', 4, 'English language', 'multichoice', 'Select the two vowels.', 'A', 'B', 'E', 'D', '', '["opt_a","opt_c"]'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'What is 2 + 3?', '5', '4', '6', '3', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'What is 7 - 2?', '5', '6', '4', '3', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'Which number comes after 9?', '10', '8', '11', '7', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'Which shape has three sides?', 'Triangle', 'Circle', 'Square', 'Rectangle', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'Which number is greater than 6?', '8', '5', '4', '3', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'What is double 4?', '8', '6', '4', '10', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'singlechoice', 'How many ones make the number 10?', '10', '5', '2', '1', '', 'opt_a'),
('lower-primary', 5, 'Mathematics', 'true_false', '5 is less than 9.', '', '', '', '', '', 'true'),
('lower-primary', 5, 'Mathematics', 'true_false', '6 + 1 equals 8.', '', '', '', '', '', 'false'),
('lower-primary', 5, 'Mathematics', 'multichoice', 'Select the two even numbers.', '2', '3', '4', '5', '', '["opt_a","opt_c"]'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'Parents and their children form a what?', 'Family', 'Market', 'Hospital', 'Farm', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'Who teaches pupils in school?', 'Teacher', 'Driver', 'Tailor', 'Farmer', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'Where should a sick person receive medical care?', 'Hospital', 'Cinema', 'Garage', 'Stadium', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'What should a road user do when the traffic light is red?', 'Stop', 'Run', 'Turn anywhere', 'Ignore it', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'What are the colours of the Nigerian flag?', 'Green and white', 'Red and blue', 'Yellow and black', 'Purple and white', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'Who helps to maintain law and order?', 'Police officer', 'Carpenter', 'Chef', 'Musician', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'singlechoice', 'Which action shows good behaviour at home?', 'Helping with chores', 'Breaking plates', 'Shouting at everyone', 'Wasting food', '', 'opt_a'),
('lower-primary', 6, 'Social Studies', 'true_false', 'Children should share and help one another.', '', '', '', '', '', 'true'),
('lower-primary', 6, 'Social Studies', 'true_false', 'It is safe to cross a road without looking.', '', '', '', '', '', 'false'),
('lower-primary', 6, 'Social Studies', 'multichoice', 'Select the two community helpers.', 'Doctor', 'Teacher', 'Football', 'Table', '', '["opt_a","opt_b"]'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'Which of these is a primary colour?', 'Red', 'Green', 'Brown', 'Purple', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'Which tool is commonly used for drawing?', 'Pencil', 'Spoon', 'Comb', 'Cup', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'Which material can be shaped by modelling?', 'Clay', 'Water', 'Smoke', 'Light', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'What do we call the steady beat in music?', 'Rhythm', 'Silence', 'Picture', 'Colour', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'What colour is made by mixing red and yellow?', 'Orange', 'Green', 'Purple', 'Black', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'Folding paper to make an object is a form of what?', 'Craft', 'Football', 'Cooking', 'Swimming', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'singlechoice', 'Which activity uses body movement to music?', 'Dance', 'Reading', 'Counting', 'Sleeping', '', 'opt_a'),
('lower-primary', 7, 'Cultural and Creative Art', 'true_false', 'Scissors should be used carefully during art work.', '', '', '', '', '', 'true'),
('lower-primary', 7, 'Cultural and Creative Art', 'true_false', 'Mixing blue and yellow makes red.', '', '', '', '', '', 'false'),
('lower-primary', 7, 'Cultural and Creative Art', 'multichoice', 'Select the two primary colours.', 'Red', 'Blue', 'Green', 'Orange', '', '["opt_a","opt_b"]'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which of these is a living thing?', 'Plant', 'Stone', 'Cup', 'Chair', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which object provides natural light?', 'Sun', 'Book', 'Desk', 'Shoe', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which of these is an electronic device?', 'Computer', 'Broom', 'Plate', 'Basket', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which computer part is used for typing?', 'Keyboard', 'Monitor', 'Speaker', 'Mouse pad', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which tool is used to hit a nail?', 'Hammer', 'Ruler', 'Brush', 'Cup', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which part holds a plant firmly in the soil?', 'Roots', 'Petals', 'Fruit', 'Bud', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'singlechoice', 'Which computer device is used to point and click?', 'Mouse', 'Printer', 'Speaker', 'Flash drive', '', 'opt_a'),
('lower-primary', 8, 'Basic Sci & Tech', 'true_false', 'Washing the hands helps to remove dirt and germs.', '', '', '', '', '', 'true'),
('lower-primary', 8, 'Basic Sci & Tech', 'true_false', 'Children should play with electric sockets.', '', '', '', '', '', 'false'),
('lower-primary', 8, 'Basic Sci & Tech', 'multichoice', 'Select the two living things.', 'Bird', 'Tree', 'Toy car', 'Bottle', '', '["opt_a","opt_b"]'),
('lower-primary', 9, 'biology', 'singlechoice', 'What is the study of living things called?', 'Biology', 'Geography', 'History', 'Music', '', 'opt_a'),
('lower-primary', 9, 'biology', 'singlechoice', 'Which part of a plant mainly makes food?', 'Leaf', 'Root', 'Flower pot', 'Stone', '', 'opt_a'),
('lower-primary', 9, 'biology', 'singlechoice', 'How many eyes does a healthy human normally have?', 'Two', 'One', 'Three', 'Four', '', 'opt_a'),
('lower-primary', 9, 'biology', 'singlechoice', 'Where does a fish normally live?', 'Water', 'Tree', 'Desert', 'Nest', '', 'opt_a'),
('lower-primary', 9, 'biology', 'singlechoice', 'What is a young dog called?', 'Puppy', 'Kitten', 'Calf', 'Chick', '', 'opt_a'),
('lower-primary', 9, 'biology', 'singlechoice', 'What do humans take in when they breathe?', 'Air', 'Sand', 'Wood', 'Soil', '', 'opt_a'),
('lower-primary', 9, 'biology', 'singlechoice', 'Which plant part holds the plant in the soil?', 'Root', 'Fruit', 'Flower', 'Seed coat', '', 'opt_a'),
('lower-primary', 9, 'biology', 'true_false', 'Plants need water to grow well.', '', '', '', '', '', 'true'),
('lower-primary', 9, 'biology', 'true_false', 'A chair is a living thing.', '', '', '', '', '', 'false'),
('lower-primary', 9, 'biology', 'multichoice', 'Select the two animals.', 'Cat', 'Goat', 'Table', 'Pencil', '', '["opt_a","opt_b"]'),
('year-6', 1, 'Basic science', 'singlechoice', 'What is the process by which green plants make food?', 'Photosynthesis', 'Respiration', 'Digestion', 'Germination', '', 'opt_a'),
('year-6', 1, 'Basic science', 'singlechoice', 'Which force pulls objects towards the Earth?', 'Gravity', 'Friction', 'Magnetism', 'Upthrust', '', 'opt_a'),
('year-6', 1, 'Basic science', 'singlechoice', 'Which organ pumps blood around the human body?', 'Heart', 'Lungs', 'Kidney', 'Stomach', '', 'opt_a'),
('year-6', 1, 'Basic science', 'singlechoice', 'Which gas is needed for human respiration?', 'Oxygen', 'Carbon dioxide', 'Nitrogen only', 'Hydrogen', '', 'opt_a'),
('year-6', 1, 'Basic science', 'singlechoice', 'What change occurs when liquid water becomes water vapour?', 'Evaporation', 'Freezing', 'Condensation', 'Melting', '', 'opt_a'),
('year-6', 1, 'Basic science', 'singlechoice', 'The Earth travels around which star?', 'Sun', 'Moon', 'Mars', 'Venus', '', 'opt_a'),
('year-6', 1, 'Basic science', 'singlechoice', 'A seesaw is an example of which simple machine?', 'Lever', 'Pulley', 'Screw', 'Wedge', '', 'opt_a'),
('year-6', 1, 'Basic science', 'true_false', 'Sound normally needs a medium through which to travel.', '', '', '', '', '', 'true'),
('year-6', 1, 'Basic science', 'true_false', 'A magnet attracts every kind of metal.', '', '', '', '', '', 'false'),
('year-6', 1, 'Basic science', 'multichoice', 'Select the two renewable sources of energy.', 'Solar energy', 'Wind energy', 'Coal', 'Petrol', '', '["opt_a","opt_b"]'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'Which instrument is used to measure or construct angles?', 'Protractor', 'Compass only', 'T-square', 'Set square only', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'Which hand tool is designed mainly for cutting wood?', 'Tenon saw', 'Spanner', 'Pliers', 'Screwdriver', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'Which item protects the eyes during workshop work?', 'Safety goggles', 'Apron only', 'Sandals', 'Cap', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'What is the first step in solving a design problem?', 'Identify the problem', 'Paint the product', 'Sell the product', 'Discard all ideas', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'Which mechanism transmits rotary motion using toothed wheels?', 'Gear system', 'Lever', 'Wedge', 'Inclined plane', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'Which material is a good electrical conductor?', 'Copper', 'Rubber', 'Dry wood', 'Plastic', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'singlechoice', 'Which simple machine can help lift a load with a rope?', 'Pulley', 'Wedge', 'Screwdriver', 'Wheel only', '', 'opt_a'),
('year-6', 2, 'Basic Technology', 'true_false', 'Measuring carefully before cutting helps to reduce waste.', '', '', '', '', '', 'true'),
('year-6', 2, 'Basic Technology', 'true_false', 'Electrical equipment should be handled with wet hands.', '', '', '', '', '', 'false'),
('year-6', 2, 'Basic Technology', 'multichoice', 'Select the two simple machines.', 'Lever', 'Pulley', 'Battery', 'Bulb', '', '["opt_a","opt_b"]'),
('year-6', 3, 'French', 'singlechoice', 'What does Je m''appelle mean?', 'My name is', 'I am hungry', 'I am going', 'Good evening', '', 'opt_a'),
('year-6', 3, 'French', 'singlechoice', 'What does Comment allez-vous? mean?', 'How are you?', 'Where are you?', 'What is your name?', 'How old are you?', '', 'opt_a'),
('year-6', 3, 'French', 'singlechoice', 'Which French word means Wednesday?', 'Mercredi', 'Lundi', 'Samedi', 'Dimanche', '', 'opt_a'),
('year-6', 3, 'French', 'singlechoice', 'What does bibliothèque mean?', 'Library', 'Hospital', 'Market', 'Kitchen', '', 'opt_a'),
('year-6', 3, 'French', 'singlechoice', 'Choose the correct expression for a girl.', 'Une fille', 'Un fille', 'Le fille', 'Des garçon', '', 'opt_a'),
('year-6', 3, 'French', 'singlechoice', 'What does nous sommes mean?', 'We are', 'They have', 'I am', 'You go', '', 'opt_a'),
('year-6', 3, 'French', 'singlechoice', 'Which number is dix-sept?', '17', '7', '10', '27', '', 'opt_a'),
('year-6', 3, 'French', 'true_false', 'J''ai douze ans means I am twelve years old.', '', '', '', '', '', 'true'),
('year-6', 3, 'French', 'true_false', 'Rouge is the French word for black.', '', '', '', '', '', 'false'),
('year-6', 3, 'French', 'multichoice', 'Select the two French definite articles.', 'Le', 'La', 'Un', 'Une', '', '["opt_a","opt_b"]'),
('year-6', 4, 'English language', 'singlechoice', 'Which word is the adjective in the phrase the bright sun?', 'Bright', 'Sun', 'The', 'Phrase', '', 'opt_a'),
('year-6', 4, 'English language', 'singlechoice', 'What is the past tense of go?', 'Went', 'Gone', 'Goes', 'Going', '', 'opt_a'),
('year-6', 4, 'English language', 'singlechoice', 'Which word is closest in meaning to rapid?', 'Fast', 'Slow', 'Weak', 'Late', '', 'opt_a'),
('year-6', 4, 'English language', 'singlechoice', 'What is the collective noun for a group of lions?', 'Pride', 'Herd', 'School', 'Pack', '', 'opt_a'),
('year-6', 4, 'English language', 'singlechoice', 'Which prefix changes happy to its opposite?', 'un-', 're-', 'pre-', 'mis-', '', 'opt_a'),
('year-6', 4, 'English language', 'singlechoice', 'Which punctuation mark separates items in a simple list?', 'Comma', 'Question mark', 'Apostrophe', 'Hyphen', '', 'opt_a'),
('year-6', 4, 'English language', 'singlechoice', 'Which pronoun can replace Amina and Tunde?', 'They', 'He', 'She', 'It', '', 'opt_a'),
('year-6', 4, 'English language', 'true_false', 'A pronoun can replace a noun in a sentence.', '', '', '', '', '', 'true'),
('year-6', 4, 'English language', 'true_false', 'Their is the contraction of they are.', '', '', '', '', '', 'false'),
('year-6', 4, 'English language', 'multichoice', 'Select the two conjunctions.', 'And', 'Quickly', 'But', 'Table', '', '["opt_a","opt_c"]'),
('year-6', 5, 'Mathematics', 'singlechoice', 'What is 3/4 + 1/4?', '1', '1/2', '4/8', '2', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'singlechoice', 'What is 15% of 200?', '30', '15', '20', '35', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'singlechoice', 'What is the area of a rectangle measuring 8 cm by 5 cm?', '40 square centimetres', '26 square centimetres', '13 square centimetres', '80 square centimetres', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'singlechoice', 'Which fraction is equal to 0.75?', '3/4', '1/4', '2/5', '7/10', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'singlechoice', 'What is the highest common factor of 12 and 18?', '6', '3', '9', '36', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'singlechoice', 'What is the mean of 4, 6 and 8?', '6', '5', '7', '18', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'singlechoice', 'What is the perimeter of a square with side 7 cm?', '28 cm', '49 cm', '14 cm', '21 cm', '', 'opt_a'),
('year-6', 5, 'Mathematics', 'true_false', '29 is a prime number.', '', '', '', '', '', 'true'),
('year-6', 5, 'Mathematics', 'true_false', '2.5 multiplied by 4 equals 100.', '', '', '', '', '', 'false'),
('year-6', 5, 'Mathematics', 'multichoice', 'Select the two factors of 12.', '3', '5', '4', '7', '', '["opt_a","opt_c"]'),
('year-6', 6, 'Social Studies', 'singlechoice', 'Which arm of government makes laws?', 'Legislature', 'Executive', 'Judiciary', 'Civil service', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'singlechoice', 'In a democracy, citizens choose leaders mainly through what?', 'Elections', 'Inheritance', 'Lottery', 'Appointment for life', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'singlechoice', 'How many states are in Nigeria?', '36', '30', '37', '40', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'singlechoice', 'What word describes a people''s total way of life?', 'Culture', 'Weather', 'Transport', 'Population', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'singlechoice', 'Which is a peaceful way to resolve disagreement?', 'Dialogue', 'Violence', 'Insults', 'Vandalism', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'singlechoice', 'Which organisation promotes cooperation among West African countries?', 'ECOWAS', 'FIFA', 'WHO only', 'OPEC only', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'singlechoice', 'Which action protects public property?', 'Using it responsibly', 'Writing on it', 'Breaking it', 'Stealing it', '', 'opt_a'),
('year-6', 6, 'Social Studies', 'true_false', 'Respect for cultural differences can promote peace.', '', '', '', '', '', 'true'),
('year-6', 6, 'Social Studies', 'true_false', 'Vandalism is a way of protecting public property.', '', '', '', '', '', 'false'),
('year-6', 6, 'Social Studies', 'multichoice', 'Select the two civic responsibilities.', 'Obeying lawful rules', 'Protecting public property', 'Destroying road signs', 'Buying votes', '', '["opt_a","opt_b"]'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'Which colour is complementary to red on a traditional colour wheel?', 'Green', 'Orange', 'Purple', 'Yellow', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'What does texture describe in visual art?', 'Surface quality', 'Only colour', 'Only size', 'Sound level', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'How many lines make up a standard musical staff?', 'Five', 'Four', 'Six', 'Seven', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'What is the written text of a drama called?', 'Script', 'Canvas', 'Chorus only', 'Gallery', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'What are the main materials for papier-mache?', 'Paper and paste', 'Metal and oil', 'Glass and sand', 'Wood and nails', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'Which technique creates the appearance of depth in a drawing?', 'Perspective', 'Repetition only', 'Tracing only', 'Stencilling only', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'singlechoice', 'Tie-dye is an example of which process?', 'Resist dyeing', 'Carving', 'Casting metal', 'Weaving only', '', 'opt_a'),
('year-6', 7, 'Cultural and Creative Art', 'true_false', 'Red and yellow are warm colours.', '', '', '', '', '', 'true'),
('year-6', 7, 'Cultural and Creative Art', 'true_false', 'The audience members are the actors performing a play.', '', '', '', '', '', 'false'),
('year-6', 7, 'Cultural and Creative Art', 'multichoice', 'Select the two performing arts.', 'Music', 'Drama', 'Painting', 'Pottery', '', '["opt_a","opt_b"]'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'What is a complete path through which electric current flows called?', 'Circuit', 'Lever', 'Network cable only', 'Magnet', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'What process allows green plants to make food?', 'Photosynthesis', 'Condensation', 'Erosion', 'Fermentation', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'Which computer component is often called the brain of the computer?', 'CPU', 'Keyboard', 'Monitor', 'Speaker', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'Which is a renewable energy source?', 'Solar energy', 'Coal', 'Diesel', 'Kerosene', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'Which force opposes motion between surfaces?', 'Friction', 'Gravity only', 'Upthrust', 'Magnetism only', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'Which application is best suited to calculations in rows and columns?', 'Spreadsheet', 'Paint program', 'Music player', 'Web camera', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'singlechoice', 'A ramp is an example of which simple machine?', 'Inclined plane', 'Pulley', 'Wheel and axle', 'Screw', '', 'opt_a'),
('year-6', 8, 'Basic Sci & Tech', 'true_false', 'A strong password should be kept private.', '', '', '', '', '', 'true'),
('year-6', 8, 'Basic Sci & Tech', 'true_false', 'An open electric circuit allows current to flow continuously.', '', '', '', '', '', 'false'),
('year-6', 8, 'Basic Sci & Tech', 'multichoice', 'Select the two computer input devices.', 'Keyboard', 'Mouse', 'Monitor', 'Printer', '', '["opt_a","opt_b"]'),
('ss1', 1, 'Basic science', 'singlechoice', 'What is the SI unit of length?', 'Metre', 'Litre', 'Gram', 'Second', '', 'opt_a'),
('ss1', 1, 'Basic science', 'singlechoice', 'Which expression gives density?', 'Mass divided by volume', 'Volume divided by mass', 'Mass times volume', 'Force divided by time', '', 'opt_a'),
('ss1', 1, 'Basic science', 'singlechoice', 'In which organelle does photosynthesis mainly occur?', 'Chloroplast', 'Nucleus', 'Ribosome', 'Vacuole', '', 'opt_a'),
('ss1', 1, 'Basic science', 'singlechoice', 'What does an acid do to blue litmus paper?', 'Turns it red', 'Turns it green', 'Turns it white', 'Does not affect any indicator', '', 'opt_a'),
('ss1', 1, 'Basic science', 'singlechoice', 'What is the basic structural and functional unit of life?', 'Cell', 'Tissue', 'Organ', 'System', '', 'opt_a'),
('ss1', 1, 'Basic science', 'singlechoice', 'What is the SI unit of force?', 'Newton', 'Joule', 'Watt', 'Pascal', '', 'opt_a'),
('ss1', 1, 'Basic science', 'singlechoice', 'Which statement describes conservation of energy?', 'Energy changes form but is not created or destroyed', 'Energy is always lost completely', 'Energy can be created from nothing', 'Only heat is conserved', '', 'opt_a'),
('ss1', 1, 'Basic science', 'true_false', 'An object''s mass remains the same when its location changes.', '', '', '', '', '', 'true'),
('ss1', 1, 'Basic science', 'true_false', 'Boiling water is a chemical change.', '', '', '', '', '', 'false'),
('ss1', 1, 'Basic science', 'multichoice', 'Select the two renewable energy sources.', 'Solar energy', 'Wind energy', 'Coal', 'Natural gas', '', '["opt_a","opt_b"]'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'Which drawing method shows front, plan and side views separately?', 'Orthographic projection', 'Perspective sketch', 'Freehand lettering', 'Pictorial colouring', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'What does a scale of 1:2 mean on a drawing?', 'The drawing is half the actual size', 'The drawing is twice the actual size', 'The drawing and object are equal', 'The object has no dimensions', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'Which material is ferrous?', 'Mild steel', 'Aluminium', 'Copper', 'Brass', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'Which equation states Ohm''s law?', 'V = IR', 'P = MV', 'F = MA only', 'D = M + V', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'Which instrument accurately measures internal and external diameters?', 'Vernier caliper', 'Try square', 'Protractor', 'Divider only', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'Which machine tool is mainly used for turning cylindrical work?', 'Lathe', 'Drilling machine', 'Grinding wheel only', 'Furnace', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'singlechoice', 'Which protective item should be worn when grinding?', 'Safety goggles', 'Loose scarf', 'Open sandals', 'Necklace', '', 'opt_a'),
('ss1', 2, 'Basic Technology', 'true_false', 'Accurate dimensions are essential in a technical drawing.', '', '', '', '', '', 'true'),
('ss1', 2, 'Basic Technology', 'true_false', 'A file is mainly used to drive nails into wood.', '', '', '', '', '', 'false'),
('ss1', 2, 'Basic Technology', 'multichoice', 'Select the two permanent joining methods.', 'Riveting', 'Welding', 'Nut and bolt', 'Screw fastening', '', '["opt_a","opt_b"]'),
('ss1', 3, 'French', 'singlechoice', 'Choose the correct passe compose form of aller for je.', 'Je suis alle(e)', 'J''ai aller', 'Je vais alle', 'Je suis allerons', '', 'opt_a'),
('ss1', 3, 'French', 'singlechoice', 'Complete the sentence: Nous ___ un livre.', 'avons', 'avez', 'a', 'ai', '', 'opt_a'),
('ss1', 3, 'French', 'singlechoice', 'Which sentence is in the near future tense?', 'Je vais etudier', 'J''etudie hier', 'J''ai etudier demain', 'Je suis etude', '', 'opt_a'),
('ss1', 3, 'French', 'singlechoice', 'What is the opposite of tot?', 'Tard', 'Vite', 'Bien', 'Beaucoup', '', 'opt_a'),
('ss1', 3, 'French', 'singlechoice', 'What does au marche mean?', 'At the market', 'At school', 'At home', 'At the station', '', 'opt_a'),
('ss1', 3, 'French', 'singlechoice', 'Choose the correct feminine nationality adjective.', 'Francaise', 'Francais', 'France', 'Francaises for one girl', '', 'opt_a'),
('ss1', 3, 'French', 'singlechoice', 'What does Ou habites-tu? ask?', 'Where do you live?', 'What do you eat?', 'How old are you?', 'When do you travel?', '', 'opt_a'),
('ss1', 3, 'French', 'true_false', 'Il fait chaud describes hot weather.', '', '', '', '', '', 'true'),
('ss1', 3, 'French', 'true_false', 'Manger means to sleep.', '', '', '', '', '', 'false'),
('ss1', 3, 'French', 'multichoice', 'Select the two Francophone countries.', 'Senegal', 'Cote d''Ivoire', 'Brazil', 'Japan', '', '["opt_a","opt_b"]'),
('ss1', 4, 'English language', 'singlechoice', 'Which figure of speech appears in Time is a thief?', 'Metaphor', 'Simile', 'Personification only', 'Hyperbole', '', 'opt_a'),
('ss1', 4, 'English language', 'singlechoice', 'Which group of words can stand alone as a complete sentence?', 'Independent clause', 'Dependent clause', 'Prepositional phrase', 'Adjective phrase', '', 'opt_a'),
('ss1', 4, 'English language', 'singlechoice', 'Choose the sentence with correct subject-verb agreement.', 'The list of items is on the desk.', 'The list of items are on the desk.', 'The boys plays football.', 'She walk to school.', '', 'opt_a'),
('ss1', 4, 'English language', 'singlechoice', 'Change Ada said, I am ready to reported speech.', 'Ada said that she was ready.', 'Ada said that I am ready.', 'Ada says she ready.', 'Ada said she is readiness.', '', 'opt_a'),
('ss1', 4, 'English language', 'singlechoice', 'Which word is closest in meaning to diligent?', 'Hard-working', 'Careless', 'Noisy', 'Uncertain', '', 'opt_a'),
('ss1', 4, 'English language', 'singlechoice', 'Which word is opposite in meaning to scarce?', 'Abundant', 'Rare', 'Limited', 'Small', '', 'opt_a'),
('ss1', 4, 'English language', 'singlechoice', 'Which punctuation mark can link two closely related independent clauses?', 'Semicolon', 'Apostrophe', 'Question mark', 'Quotation mark', '', 'opt_a'),
('ss1', 4, 'English language', 'true_false', 'A finite verb shows tense and agrees with a subject where agreement applies.', '', '', '', '', '', 'true'),
('ss1', 4, 'English language', 'true_false', 'Information is normally used as a countable noun with an.', '', '', '', '', '', 'false'),
('ss1', 4, 'English language', 'multichoice', 'Select the two subordinating conjunctions.', 'Although', 'And', 'Because', 'But', '', '["opt_a","opt_c"]'),
('ss1', 5, 'Mathematics', 'singlechoice', 'Solve 3x + 5 = 20.', 'x = 5', 'x = 3', 'x = 15', 'x = 25/3', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'singlechoice', 'Factorise x^2 - 9.', '(x - 3)(x + 3)', '(x - 9)(x + 1)', '(x - 3)^2', '(x + 9)(x - 1)', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'singlechoice', 'Find the slope of the line through (1, 2) and (3, 6).', '2', '1', '3', '4', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'singlechoice', 'Evaluate 2^3 x 2^2.', '32', '16', '64', '8', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'singlechoice', 'If x + y = 7 and x - y = 1, what is x?', '4', '3', '6', '8', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'singlechoice', 'Find the simple interest on NGN 10,000 at 5% per annum for 2 years.', 'NGN 1,000', 'NGN 500', 'NGN 2,000', 'NGN 10,500', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'singlechoice', 'What is the probability of rolling an even number on a fair six-sided die?', '1/2', '1/3', '2/3', '1/6', '', 'opt_a'),
('ss1', 5, 'Mathematics', 'true_false', 'The principal square root of 49 is 7.', '', '', '', '', '', 'true'),
('ss1', 5, 'Mathematics', 'true_false', 'Every integer is a rational number.', '', '', '', '', '', 'true'),
('ss1', 5, 'Mathematics', 'multichoice', 'Select the two solutions of x^2 = 9.', '3', '9', '-3', '0', '', '["opt_a","opt_c"]'),
('ss1', 6, 'Social Studies', 'singlechoice', 'What is a constitution?', 'The fundamental body of rules governing a state', 'A list of market prices', 'A school timetable', 'A private letter', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'singlechoice', 'What does the rule of law require?', 'Everyone is subject to the law', 'Leaders are above the law', 'Only citizens obey laws', 'Courts follow personal wishes', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'singlechoice', 'Which is a recognised way of acquiring citizenship?', 'Naturalisation', 'Buying an election', 'Ignoring immigration law', 'Joining a club', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'singlechoice', 'What is a major purpose of a pressure group?', 'To influence public policy', 'To conduct every election', 'To replace all courts', 'To command the armed forces', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'singlechoice', 'Why is separation of powers important?', 'It limits concentration and abuse of power', 'It removes all laws', 'It gives one arm total control', 'It prevents public accountability', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'singlechoice', 'Which body is responsible for organising public elections?', 'Electoral commission', 'A sports club', 'A trade shop', 'A private household', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'singlechoice', 'Human trafficking is primarily a violation of what?', 'Human rights', 'Weather rules', 'Sporting rules', 'Road design', '', 'opt_a'),
('ss1', 6, 'Social Studies', 'true_false', 'Responsible civic participation can strengthen democracy.', '', '', '', '', '', 'true'),
('ss1', 6, 'Social Studies', 'true_false', 'Corruption improves the delivery of public services.', '', '', '', '', '', 'false'),
('ss1', 6, 'Social Studies', 'multichoice', 'Select the two democratic values.', 'Accountability', 'Tolerance', 'Intimidation', 'Vote buying', '', '["opt_a","opt_b"]');

DROP TEMPORARY TABLE IF EXISTS tmp_schoollift_demo_question_scopes;
CREATE TEMPORARY TABLE tmp_schoollift_demo_question_scopes
ENGINE=InnoDB
AS
SELECT DISTINCT
    cs.id AS class_section_id,
    cs.class_id,
    cs.section_id,
    sgs.subject_id,
    cm.audience
FROM subject_group_class_sections AS sgcs
INNER JOIN subject_group_subjects AS sgs
    ON sgs.subject_group_id = sgcs.subject_group_id
   AND sgs.session_id = sgcs.session_id
INNER JOIN class_sections AS cs
    ON cs.id = sgcs.class_section_id
INNER JOIN classes AS c
    ON c.id = cs.class_id
INNER JOIN tmp_schoollift_demo_class_map AS cm
    ON cm.class_id = c.id
   AND LOWER(TRIM(cm.expected_class_name)) = LOWER(TRIM(c.class))
INNER JOIN subjects AS subject_record
    ON subject_record.id = sgs.subject_id
WHERE EXISTS (
    SELECT 1
    FROM tmp_schoollift_demo_question_templates AS template_check
    WHERE template_check.audience = cm.audience
      AND template_check.subject_id = sgs.subject_id
      AND LOWER(TRIM(template_check.expected_subject_name)) = LOWER(TRIM(subject_record.name))
);

START TRANSACTION;

INSERT INTO questions
    (staff_id, subject_id, question_type, class_id, section_id, class_section_id,
     question, opt_a, opt_b, opt_c, opt_d, opt_e, correct, created_at, updated_at)
SELECT
    @schoollift_demo_question_bank_staff_id,
    scope_record.subject_id,
    template_record.question_type,
    scope_record.class_id,
    scope_record.section_id,
    scope_record.class_section_id,
    template_record.question,
    template_record.opt_a,
    template_record.opt_b,
    template_record.opt_c,
    template_record.opt_d,
    template_record.opt_e,
    template_record.correct,
    NOW(),
    CURDATE()
FROM tmp_schoollift_demo_question_scopes AS scope_record
INNER JOIN tmp_schoollift_demo_question_templates AS template_record
    ON template_record.audience = scope_record.audience
   AND template_record.subject_id = scope_record.subject_id
WHERE @schoollift_demo_question_bank_site_ok = 1
  AND NOT EXISTS (
      SELECT 1
      FROM questions AS existing_question
      WHERE existing_question.class_id = scope_record.class_id
        AND existing_question.section_id = scope_record.section_id
        AND existing_question.subject_id = scope_record.subject_id
        AND existing_question.question_type = template_record.question_type
        AND existing_question.question = template_record.question
  )
ORDER BY
    scope_record.class_id,
    scope_record.section_id,
    scope_record.subject_id,
    template_record.question_type,
    template_record.question;

SET @schoollift_demo_question_bank_inserted := ROW_COUNT();

COMMIT;

SELECT
    CASE
        WHEN @schoollift_demo_question_bank_site_ok = 0
            THEN 'STOPPED: this database is not identified as demo.schoollift.com.ng; no questions were inserted.'
        ELSE CONCAT(
            'COMPLETE: ',
            @schoollift_demo_question_bank_inserted,
            ' demo questions inserted; existing exact matches were skipped.'
        )
    END AS question_bank_import_result;

DROP TEMPORARY TABLE IF EXISTS tmp_schoollift_demo_question_scopes;
DROP TEMPORARY TABLE IF EXISTS tmp_schoollift_demo_question_templates;
DROP TEMPORARY TABLE IF EXISTS tmp_schoollift_demo_class_map;
