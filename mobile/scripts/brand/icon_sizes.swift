// APP ICON SIZES from the 1024 master (work order CLIENT-START §1, frame 40-A-3).
//
// Reads build/brand/icon-1024.png (render_brand.mjs), draws it into an OPAQUE context — the App Store icon may not
// carry an alpha channel, and a screenshot PNG always does — and writes every file AppIcon.appiconset/Contents.json
// names, at the pixel size its entry asks for. Run from mobile/:  xcrun swift scripts/brand/icon_sizes.swift
import CoreGraphics
import Foundation
import ImageIO
import UniformTypeIdentifiers

let master = URL(fileURLWithPath: "build/brand/icon-1024.png")
let set = URL(fileURLWithPath: "ios/Runner/Assets.xcassets/AppIcon.appiconset")

guard let source = CGImageSourceCreateWithURL(master as CFURL, nil),
      let image = CGImageSourceCreateImageAtIndex(source, 0, nil)
else { fatalError("no master at \(master.path) — run render_brand.mjs first") }

struct Entry: Decodable { let size: String; let scale: String; let filename: String? }
struct Contents: Decodable { let images: [Entry] }

let contents = try JSONDecoder().decode(
  Contents.self, from: Data(contentsOf: set.appendingPathComponent("Contents.json")))

var written = Set<String>()
for entry in contents.images {
  guard let name = entry.filename, !written.contains(name) else { continue }
  let points = Double(entry.size.split(separator: "x")[0])!
  let scale = Double(entry.scale.dropLast())!
  let px = Int((points * scale).rounded())

  let context = CGContext(
    data: nil, width: px, height: px, bitsPerComponent: 8, bytesPerRow: 0,
    space: CGColorSpace(name: CGColorSpace.sRGB)!,
    bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)!
  context.interpolationQuality = .high
  context.draw(image, in: CGRect(x: 0, y: 0, width: px, height: px))

  let out = set.appendingPathComponent(name)
  let dest = CGImageDestinationCreateWithURL(out as CFURL, UTType.png.identifier as CFString, 1, nil)!
  CGImageDestinationAddImage(dest, context.makeImage()!, nil)
  guard CGImageDestinationFinalize(dest) else { fatalError("could not write \(name)") }
  written.insert(name)
  print("\(name) \(px)×\(px)")
}
