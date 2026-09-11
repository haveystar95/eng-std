import Flutter
import UIKit

class SceneDelegate: FlutterSceneDelegate {
  /// ССЫЛКИ `engstd://…` ПРИХОДЯТ В СЦЕНУ, НЕ В AppDelegate (наряд DAY-UI): приложение на
  /// UIScene-цикле, и `application(_:open:options:)` iOS для него не зовёт. Холодный старт — в
  /// `connectionOptions.urlContexts`; тёплый — `openURLContexts`. Обе дороги ведут в один канал.
  override func scene(
    _ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions
  ) {
    super.scene(scene, willConnectTo: session, options: connectionOptions)
    for context in connectionOptions.urlContexts {
      AppDelegate.current?.handleLink(context.url)
    }
  }

  override func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
    super.scene(scene, openURLContexts: URLContexts)
    for context in URLContexts {
      AppDelegate.current?.handleLink(context.url)
    }
  }
}
