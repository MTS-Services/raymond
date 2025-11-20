class CardDesign {

    #frontCanvas = null;
    #backCanvas = null;
    #frontCtx = null;
    #backCtx = null;

    constructor(
        cardType = null,
        frontImage = null,
        backImage = null,  
        isCombo = false; 
    ) {
        this.cardType = cardType;
        this.frontImage = frontImage;
        this.backImage = backImage;
        this.isCombo = isCombo;
    }

    setCanvases(frontCanvas, backCanvas) {
        this.#frontCanvas = this.byId(frontCanvas);
        this.#backCanvas = this.byId(backCanvas);
        this.#frontCtx = this.#frontCanvas.getContext("2d");
        this.#backCtx = this.#backCanvas.getContext("2d");
    }

    getCanvases() {
        return {
            frontCanvas: this.#frontCanvas,
            backCanvas: this.#backCanvas,
            frontCtx: this.#frontCtx,
            backCtx: this.#backCtx,
        };
    }

 
    layout(){
        return {
            photo: { xPct: 0.038, yPct: 0.14, wPx: 100, hPx: 140, radius: 12, offsetX: -5, offsetY: -45 }, 
            animal: { xPct: 0.375, yPct: 0.5, maxWidthPct: 0.36, baseFontPx: 12.47, minFontPx: 9, offsetX: 25, offsetY: -7 },
            handler: { xPct: 0.375, yPct: 0.62, maxWidthPct: 0.36, baseFontPx: 12.47, minFontPx: 9, offsetX: 25, offsetY: -2 },
 
        }
    }
    byId($id){
        return document.getElementById($id);
    }

}