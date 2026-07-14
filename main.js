 const config = {
            type: Phaser.AUTO,
            width: 800,
            height: 600,
            parent: 'game-container',
            backgroundColor: '#2d3436',
            physics: {
                default: 'arcade',
                arcade: {
                    gravity: { y: 0 },
                    debug: false
                }
            },
            scene: {
                preload: preload,
                create: create,
                update: update
            }
        };

        const game = new Phaser.Game(config);
        let objects = [];
        let background;
        let selectedObject = null;
        let cursors;
        let bgColors = ['#2d3436', '#0984e3', '#6c5ce7', '#00b894', '#fd79a8'];
        let currentBgIndex = 0;

        function preload() {
            // إنشاء خلفية متدرجة
            createBackgroundTexture(this);
            
            // إنشاء كائنات مخصصة بدلاً من تحميل صور خارجية
            createObjectTextures(this);
        }

        function createBackgroundTexture(scene) {
            const graphics = scene.add.graphics();
            
            // رسم خلفية متدرجة مع نمط
            for (let i = 0; i < 600; i += 20) {
                const alpha = 0.1 + (i / 600) * 0.2;
                graphics.fillStyle(0x000000, alpha);
                graphics.fillRect(0, i, 800, 20);
            }
            
            // إضافة نجوم للخلفية
            graphics.fillStyle(0xffffff, 0.8);
            for (let i = 0; i < 100; i++) {
                const x = Math.random() * 800;
                const y = Math.random() * 600;
                const size = Math.random() * 2;
                graphics.fillCircle(x, y, size);
            }
            
            graphics.generateTexture('background', 800, 600);
            graphics.destroy();
        }

        function createObjectTextures(scene) {
            // إنشاء كرة
            const ball = scene.add.graphics();
            ball.fillStyle(0xff6b6b, 1);
            ball.fillCircle(32, 32, 30);
            ball.fillStyle(0xff8787, 0.6);
            ball.fillCircle(40, 24, 15);
            ball.generateTexture('ball', 64, 64);
            ball.destroy();

            // إنشاء مربع
            const box = scene.add.graphics();
            box.fillStyle(0x4ecdc4, 1);
            box.fillRect(0, 0, 64, 64);
            box.fillStyle(0x95e1d3, 0.7);
            box.fillRect(8, 8, 48, 48);
            box.lineStyle(3, 0x26a69a);
            box.strokeRect(0, 0, 64, 64);
            box.generateTexture('box', 64, 64);
            box.destroy();

            // إنشاء نجمة
            const star = scene.add.graphics();
            star.fillStyle(0xffd93d, 1);
            star.beginPath();
            for (let i = 0; i < 5; i++) {
                const angle = (i * 144 - 90) * Math.PI / 180;
                const x = 32 + Math.cos(angle) * 30;
                const y = 32 + Math.sin(angle) * 30;
                if (i === 0) star.moveTo(x, y);
                else star.lineTo(x, y);
                
                const innerAngle = (i * 144 - 90 + 72) * Math.PI / 180;
                const ix = 32 + Math.cos(innerAngle) * 15;
                const iy = 32 + Math.sin(innerAngle) * 15;
                star.lineTo(ix, iy);
            }
            star.closePath();
            star.fillPath();
            star.generateTexture('star', 64, 64);
            star.destroy();

            // إنشاء مثلث
            const triangle = scene.add.graphics();
            triangle.fillStyle(0xa29bfe, 1);
            triangle.beginPath();
            triangle.moveTo(32, 5);
            triangle.lineTo(60, 55);
            triangle.lineTo(4, 55);
            triangle.closePath();
            triangle.fillPath();
            triangle.lineStyle(3, 0x6c5ce7);
            triangle.strokePath();
            triangle.generateTexture('triangle', 64, 64);
            triangle.destroy();
        }

        function create() {
            // إضافة الخلفية
            background = this.add.image(400, 300, 'background');
            background.setDisplaySize(800, 600);

            // إضافة نص تعليمات
            const instructionText = this.add.text(400, 30, 
                'اسحب الكائنات | R: إعادة ضبط | +/-: حجم | أسهم: تحريك | C: لون | Space: انفجار', 
                {
                    fontSize: '16px',
                    fill: '#fff',
                    backgroundColor: '#00000088',
                    padding: { x: 10, y: 5 }
                }
            ).setOrigin(0.5);

            // إنشاء الكائنات
            const objectTypes = ['ball', 'box', 'star', 'triangle'];
            const positions = [
                { x: 150, y: 200 },
                { x: 350, y: 200 },
                { x: 550, y: 200 },
                { x: 250, y: 400 },
                { x: 550, y: 400 }
            ];

            positions.forEach((pos, index) => {
                const type = objectTypes[index % objectTypes.length];
                const obj = this.physics.add.sprite(pos.x, pos.y, type);
                obj.setInteractive({ draggable: true });
                obj.setCollideWorldBounds(true);
                obj.setBounce(0.5);
                
                // إضافة خصائص مخصصة
                obj.originalX = pos.x;
                obj.originalY = pos.y;
                obj.rotationSpeed = (Math.random() - 0.5) * 0.02;
                
                objects.push(obj);
            });

            // إعداد السحب والإفلات
            this.input.on('drag', (pointer, gameObject, dragX, dragY) => {
                gameObject.x = dragX;
                gameObject.y = dragY;
                selectedObject = gameObject;
            });

            this.input.on('dragstart', (pointer, gameObject) => {
                gameObject.setTint(0x00ff00);
                selectedObject = gameObject;
            });

            this.input.on('dragend', (pointer, gameObject) => {
                gameObject.clearTint();
            });

            // إعداد لوحة المفاتيح
            cursors = this.input.keyboard.createCursorKeys();
            
            // مفتاح R لإعادة الضبط
            this.input.keyboard.on('keydown-R', () => {
                objects.forEach(obj => {
                    this.tweens.add({
                        targets: obj,
                        x: obj.originalX,
                        y: obj.originalY,
                        rotation: 0,
                        scale: 1,
                        duration: 500,
                        ease: 'Back.out'
                    });
                });
            });

            // مفتاح C لتغيير لون الخلفية
            this.input.keyboard.on('keydown-C', () => {
                currentBgIndex = (currentBgIndex + 1) % bgColors.length;
                this.cameras.main.setBackgroundColor(bgColors[currentBgIndex]);
            });

            // مفتاح + للتكبير
            this.input.keyboard.on('keydown-PLUS', () => {
                if (selectedObject) {
                    this.tweens.add({
                        targets: selectedObject,
                        scale: selectedObject.scale + 0.2,
                        duration: 200
                    });
                }
            });

            // مفتاح - للتصغير
            this.input.keyboard.on('keydown-MINUS', () => {
                if (selectedObject && selectedObject.scale > 0.3) {
                    this.tweens.add({
                        targets: selectedObject,
                        scale: selectedObject.scale - 0.2,
                        duration: 200
                    });
                }
            });

            // مفتاح المسافة لتأثير انفجار
            this.input.keyboard.on('keydown-SPACE', () => {
                objects.forEach(obj => {
                    const angle = Math.random() * Math.PI * 2;
                    const speed = 200 + Math.random() * 300;
                    obj.body.setVelocity(
                        Math.cos(angle) * speed,
                        Math.sin(angle) * speed
                    );
                });
            });
        }

        function update() {
            // الدوران التلقائي للكائنات
            objects.forEach(obj => {
                obj.rotation += obj.rotationSpeed;
            });

            // تحريك الكائن المحدد بالأسهم
            if (selectedObject) {
                const speed = 5;
                
                if (cursors.left.isDown) {
                    selectedObject.x -= speed;
                } else if (cursors.right.isDown) {
                    selectedObject.x += speed;
                }
                
                if (cursors.up.isDown) {
                    selectedObject.y -= speed;
                } else if (cursors.down.isDown) {
                    selectedObject.y += speed;
                }
            }

            // تقليل السرعة تدريجياً
            objects.forEach(obj => {
                if (obj.body.velocity.x !== 0 || obj.body.velocity.y !== 0) {
                    obj.body.setVelocity(
                        obj.body.velocity.x * 0.98,
                        obj.body.velocity.y * 0.98
                    );
                }
            });
        }