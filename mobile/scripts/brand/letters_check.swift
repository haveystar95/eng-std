// THE SIX LETTERS ADD UP TO THE WORD — the check render_brand.mjs promises (work order CLIENT-START §1).
//
// Each letter image is the word's line box with one letter painted: if splitting the text into spans had changed its
// shaping (kerning across the boundary), the letters would land elsewhere than in the plain word. Composites
// wordmark_0…5.png over each other at every scale and compares with wordmark.png; prints the largest channel difference.
// Run from mobile/:  xcrun swift scripts/brand/letters_check.swift
import CoreGraphics
import Foundation
import ImageIO

func pixels(_ path: String) -> (w: Int, h: Int, data: [UInt8]) {
  let src = CGImageSourceCreateWithURL(URL(fileURLWithPath: path) as CFURL, nil)!
  let img = CGImageSourceCreateImageAtIndex(src, 0, nil)!
  var data = [UInt8](repeating: 0, count: img.width * img.height * 4)
  let ctx = CGContext(
    data: &data, width: img.width, height: img.height, bitsPerComponent: 8, bytesPerRow: img.width * 4,
    space: CGColorSpace(name: CGColorSpace.sRGB)!, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
  ctx.draw(img, in: CGRect(x: 0, y: 0, width: img.width, height: img.height))
  return (img.width, img.height, data)
}

var worst = 0
for dir in ["assets/brand", "assets/brand/2.0x", "assets/brand/3.0x"] {
  let word = pixels("\(dir)/wordmark.png")
  var sum = [Double](repeating: 0, count: word.data.count)
  for k in 0..<6 {
    let letter = pixels("\(dir)/wordmark_\(k).png")
    precondition(letter.w == word.w && letter.h == word.h, "\(dir)/wordmark_\(k).png is not the word's box")
    for i in stride(from: 0, to: sum.count, by: 4) {
      // premultiplied «over»: dst = src + dst × (1 − src.a)
      let a = Double(letter.data[i + 3]) / 255
      for c in 0..<4 { sum[i + c] = Double(letter.data[i + c]) + sum[i + c] * (1 - a) }
    }
  }
  var diff = 0
  for i in 0..<sum.count { diff = max(diff, abs(Int(sum[i].rounded()) - Int(word.data[i]))) }
  worst = max(worst, diff)
  print("\(dir): \(word.w)×\(word.h), largest difference \(diff)/255")
}
exit(worst <= 8 ? 0 : 1)
