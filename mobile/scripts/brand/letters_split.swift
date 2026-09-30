// SPLIT THE WORDMARK INTO ITS SIX LETTERS — each pixel of the word to the letter that covers it (work order CLIENT-START
// §1, the «itora» reveal of frame 41-1b).
//
// Input (render_brand.mjs): assets/brand{,/2.0x,/3.0x}/wordmark.png — the word as the canvas draws it — and
// build/brand/{1,2,3}x/shape_0…5.png — each letter rendered alone in the same box, used only as a map. A pixel goes to
// the letter whose map covers it most; a pixel no map covers (the one glyph that changes shape alone, «r» before «a»)
// goes to the letter whose advance box holds its column. The six outputs are disjoint, so drawn over each other at full
// opacity they are exactly the word — letters_check.swift proves it.
// Run from mobile/:  xcrun swift scripts/brand/letters_split.swift
import CoreGraphics
import Foundation
import ImageIO
import UniformTypeIdentifiers

// Where letters 1…5 start in the word box, logical px — lib/ui/brand/wordmark_metrics.dart, kWordmarkLetterStarts.
let starts: [Double] = {
  let source = try! String(contentsOfFile: "lib/ui/brand/wordmark_metrics.dart", encoding: .utf8)
  let line = source.split(separator: "\n").first { $0.contains("kWordmarkLetterStarts") }!
  let list = line.split(separator: "[")[1].split(separator: "]")[0]
  return list.split(separator: ",").map { Double($0.trimmingCharacters(in: .whitespaces))! }
}()

struct Bitmap {
  let w: Int, h: Int
  var data: [UInt8]
}

func load(_ path: String) -> Bitmap {
  let src = CGImageSourceCreateWithURL(URL(fileURLWithPath: path) as CFURL, nil)!
  let img = CGImageSourceCreateImageAtIndex(src, 0, nil)!
  var data = [UInt8](repeating: 0, count: img.width * img.height * 4)
  let ctx = CGContext(
    data: &data, width: img.width, height: img.height, bitsPerComponent: 8, bytesPerRow: img.width * 4,
    space: CGColorSpace(name: CGColorSpace.sRGB)!, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
  ctx.draw(img, in: CGRect(x: 0, y: 0, width: img.width, height: img.height))
  return Bitmap(w: img.width, h: img.height, data: data)
}

func save(_ bitmap: Bitmap, _ path: String) {
  var data = bitmap.data
  let ctx = CGContext(
    data: &data, width: bitmap.w, height: bitmap.h, bitsPerComponent: 8, bytesPerRow: bitmap.w * 4,
    space: CGColorSpace(name: CGColorSpace.sRGB)!, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
  let dest = CGImageDestinationCreateWithURL(URL(fileURLWithPath: path) as CFURL, UTType.png.identifier as CFString, 1, nil)!
  CGImageDestinationAddImage(dest, ctx.makeImage()!, nil)
  precondition(CGImageDestinationFinalize(dest), "could not write \(path)")
}

for (scale, dir) in [(1, "assets/brand"), (2, "assets/brand/2.0x"), (3, "assets/brand/3.0x")] {
  let word = load("\(dir)/wordmark.png")
  let shapes = (0..<6).map { load("build/brand/\(scale)x/shape_\($0).png") }
  var parts = (0..<6).map { _ in Bitmap(w: word.w, h: word.h, data: [UInt8](repeating: 0, count: word.data.count)) }
  for y in 0..<word.h {
    for x in 0..<word.w {
      let i = (y * word.w + x) * 4
      if word.data[i + 3] == 0 { continue }
      var owner = -1
      var best: UInt8 = 0
      for k in 0..<6 where shapes[k].data[i + 3] > best {
        best = shapes[k].data[i + 3]
        owner = k
      }
      if owner < 0 {
        let logical = (Double(x) + 0.5) / Double(scale)
        owner = starts.filter { $0 <= logical }.count
      }
      for c in 0..<4 { parts[owner].data[i + c] = word.data[i + c] }
    }
  }
  for k in 0..<6 { save(parts[k], "\(dir)/wordmark_\(k).png") }
  print("\(dir): split into six")
}
