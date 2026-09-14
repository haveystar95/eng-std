import AppKit

// compose.swift OUT HEIGHT LEFT_LABEL LEFT.png [RIGHT_LABEL RIGHT.png]
// Images side by side, each scaled to HEIGHT, a caption above each; one image works too.
let args = CommandLine.arguments
let out = args[1]
let height = CGFloat(Double(args[2])!)
var pairs: [(String, NSImage)] = []
var i = 3
while i + 1 < args.count {
    guard let image = NSImage(contentsOfFile: args[i + 1]) else { fatalError("no image \(args[i + 1])") }
    pairs.append((args[i], image))
    i += 2
}
let caption: CGFloat = 36
let gap: CGFloat = 16
let widths: [CGFloat] = pairs.map { pair in
    let rep = pair.1.representations.first!
    return CGFloat(rep.pixelsWide) * height / CGFloat(rep.pixelsHigh)
}
let total = widths.reduce(0, +) + gap * CGFloat(max(0, pairs.count - 1))
let canvasHeight = height + caption
let bitmap = NSBitmapImageRep(bitmapDataPlanes: nil, pixelsWide: Int(total), pixelsHigh: Int(canvasHeight), bitsPerSample: 8,
                              samplesPerPixel: 4, hasAlpha: true, isPlanar: false, colorSpaceName: .deviceRGB, bytesPerRow: 0, bitsPerPixel: 0)!
NSGraphicsContext.saveGraphicsState()
NSGraphicsContext.current = NSGraphicsContext(bitmapImageRep: bitmap)
NSColor.white.setFill()
NSRect(x: 0, y: 0, width: total, height: canvasHeight).fill()
let attributes: [NSAttributedString.Key: Any] = [
    .font: NSFont.systemFont(ofSize: 15, weight: .semibold),
    .foregroundColor: NSColor(calibratedRed: 0.18, green: 0.15, blue: 0.13, alpha: 1),
]
var x: CGFloat = 0
for (index, pair) in pairs.enumerated() {
    pair.1.draw(in: NSRect(x: x, y: 0, width: widths[index], height: height))
    (pair.0 as NSString).draw(at: NSPoint(x: x + 4, y: height + 10), withAttributes: attributes)
    x += widths[index] + gap
}
NSGraphicsContext.restoreGraphicsState()
try! bitmap.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: out))
print("wrote \(out)")
