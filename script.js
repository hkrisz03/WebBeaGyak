const car = document.querySelector('.car');

function moveCar(){
    let pos = -100;
    const interval = setInterval(() => {
        if (pos >= window.innerWidth) {
            pos = -100;
        }
        pos +=5;
        car.style.left = `${pos}px`;
    }, 50);
}

moveCar();